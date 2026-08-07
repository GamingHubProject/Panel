<?php

declare(strict_types=1);

$root = dirname(__DIR__).'/resources/views';
$failures = [];
$checked = 0;

$openers = [
    'if' => 'endif', 'foreach' => 'endforeach', 'forelse' => 'endforelse',
    'unless' => 'endunless', 'section' => 'endsection', 'for' => 'endfor',
    'while' => 'endwhile', 'switch' => 'endswitch', 'auth' => 'endauth',
    'guest' => 'endguest', 'isset' => 'endisset', 'empty' => 'endempty',
    'can' => 'endcan', 'cannot' => 'endcannot', 'canany' => 'endcanany',
    'push' => 'endpush', 'prepend' => 'endprepend', 'once' => 'endonce',
    'php' => 'endphp', 'verbatim' => 'endverbatim', 'production' => 'endproduction',
    'env' => 'endenv',
];
$closers = array_flip($openers);

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    ++$checked;
    $source = preg_replace('/{{--.*?--}}/s', '', (string) file_get_contents($file->getPathname())) ?? '';
    preg_match_all('/@([A-Za-z_][A-Za-z0-9_]*)/', $source, $matches, PREG_OFFSET_CAPTURE);
    $stack = [];

    foreach ($matches[1] as [$directive, $nameOffset]) {
        $atOffset = $nameOffset - 1;

        // @empty is a forelse branch delimiter when the current block is @forelse.
        if ($directive === 'empty' && ($stack[array_key_last($stack)]['directive'] ?? null) === 'forelse') {
            continue;
        }

        // Expression-form @php(...) does not require @endphp.
        if ($directive === 'php' && str_starts_with(ltrim(substr($source, $nameOffset + strlen($directive))), '(')) {
            continue;
        }

        // Two-argument @section('title', '...') is self-contained.
        if ($directive === 'section') {
            $after = ltrim(substr($source, $nameOffset + strlen($directive)));
            if (str_starts_with($after, '(') && sectionCallHasSecondArgument($after)) {
                continue;
            }
        }

        if (isset($openers[$directive])) {
            $stack[] = ['directive' => $directive, 'offset' => $atOffset];
            continue;
        }

        if (! isset($closers[$directive])) {
            continue;
        }

        $expected = $closers[$directive];
        $current = $stack[array_key_last($stack)]['directive'] ?? null;
        if ($current !== $expected) {
            $failures[] = sprintf(
                '%s: unexpected @%s near byte %d; expected closure for %s.',
                $file->getPathname(),
                $directive,
                $atOffset,
                $current === null ? 'no open block' : '@'.$current,
            );
            break;
        }

        array_pop($stack);
    }

    if ($stack !== []) {
        $remaining = implode(', ', array_map(static fn (array $item): string => '@'.$item['directive'], $stack));
        $failures[] = $file->getPathname().': unclosed directives: '.$remaining.'.';
    }
}

foreach ($failures as $failure) {
    echo 'FAIL '.$failure.PHP_EOL;
}

if ($failures === []) {
    echo "PASS {$checked} Blade files have balanced directive nesting".PHP_EOL;
}

exit($failures === [] ? 0 : 1);

function sectionCallHasSecondArgument(string $call): bool
{
    $depth = 0;
    $quote = null;
    $escaped = false;

    foreach (str_split($call) as $character) {
        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($character === "'" || $character === '"') {
            $quote = $character;
        } elseif ($character === '(') {
            ++$depth;
        } elseif ($character === ')') {
            --$depth;
            if ($depth === 0) {
                return false;
            }
        } elseif ($character === ',' && $depth === 1) {
            return true;
        }
    }

    return false;
}
