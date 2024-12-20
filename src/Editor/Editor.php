<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Editor;

use LastDragon_ru\LaraASP\Documentator\Utils\Text;
use Override;
use Stringable;

use function array_merge;
use function array_push;
use function array_reverse;
use function array_slice;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_string;
use function mb_substr;
use function rtrim;
use function usort;

use const PHP_INT_MAX;

/**
 * @readonly Waiting for no PHP 8.2 (https://github.com/LastDragon-ru/lara-asp/issues/190)
 */
class Editor implements Stringable {
    /**
     * @var list<string>
     */
    protected readonly array $lines;

    /**
     * @param list<string>|string $content
     */
    final public function __construct(
        array|string $content,
        protected readonly int $startLine = 0,
        protected readonly string $endOfLine = "\n",
    ) {
        $this->lines = is_string($content) ? Text::getLines($content) : $content;
    }

    #[Override]
    public function __toString(): string {
        return implode($this->endOfLine, $this->lines);
    }

    /**
     * @param iterable<array-key, Coordinate> $location
     */
    public function getText(iterable $location): ?string {
        // Select
        $selected = null;

        foreach ($location as $coordinate) {
            $number = $coordinate->line - $this->startLine;

            if (isset($this->lines[$number])) {
                $selected[] = mb_substr($this->lines[$number], $coordinate->offset, $coordinate->length);
            } else {
                $selected = null;
                break;
            }
        }

        if ($selected === null) {
            return null;
        }

        // Return
        return implode($this->endOfLine, $selected);
    }

    /**
     * @param iterable<array-key, array{iterable<array-key, Coordinate>, ?string}> $changes
     *
     * @return new<static>
     */
    public function mutate(iterable $changes): static {
        // Modify
        $lines   = $this->lines;
        $changes = $this->prepare($changes);
        $changes = $this->removeOverlaps($changes);
        $changes = $this->expand($changes);

        foreach ($changes as [$coordinate, $text]) {
            // Append?
            if ($coordinate->line === PHP_INT_MAX) {
                array_push($lines, ...$text);
                continue;
            }

            // Change
            $number  = $coordinate->line - $this->startLine;
            $line    = $lines[$number] ?? '';
            $count   = count($text);
            $prefix  = mb_substr($line, 0, $coordinate->offset);
            $suffix  = $coordinate->length !== null
                ? mb_substr($line, $coordinate->offset + $coordinate->length)
                : '';
            $padding = mb_substr($line, 0, $coordinate->padding);

            if ($count > 1) {
                $insert = [];

                for ($t = 0; $t < $count; $t++) {
                    $insert[] = match (true) {
                        $t === 0          => rtrim($prefix.$text[$t]),
                        $t === $count - 1 => rtrim($padding.$text[$t].$suffix),
                        default           => rtrim($padding.$text[$t]),
                    };
                }

                array_splice($lines, $number, 1, $insert);
            } elseif ($count === 1) {
                $lines[$number] = rtrim($prefix.$text[0].$suffix);
            } elseif (($prefix !== '' && $prefix !== $padding) || $suffix !== '') {
                $lines[$number] = rtrim($prefix.$suffix);
            } else {
                unset($lines[$number]);
            }
        }

        // Return
        return new static(array_values($lines), $this->startLine, $this->endOfLine);
    }

    /**
     * @param iterable<array-key, array{iterable<array-key, Coordinate>, ?string}> $changes
     *
     * @return list<array{list<Coordinate>, ?string}>
     */
    protected function prepare(iterable $changes): array {
        $prepared = [];

        foreach ($changes as [$location, $text]) {
            $coordinates = [];

            foreach ($location as $coordinate) {
                $coordinates[] = $coordinate;
            }

            if ($coordinates !== []) {
                $prepared[] = [$coordinates, $text];
            }
        }

        return array_reverse($prepared);
    }

    /**
     * @param array<int, array{list<Coordinate>, ?string}> $changes
     *
     * @return list<array{Coordinate, list<string>}>
     */
    protected function expand(array $changes): array {
        $expanded = [];
        $append   = [];
        $sort     = static function (Coordinate $a, Coordinate $b): int {
            $result = $a->line <=> $b->line;
            $result = $result === 0
                ? $a->offset <=> $b->offset
                : $result;

            return $result;
        };

        foreach ($changes as [$coordinates, $text]) {
            $text = match (true) {
                $text === null => [],
                $text === ''   => [''],
                default        => Text::getLines($text),
            };

            usort($coordinates, $sort);

            for ($i = 0, $c = count($coordinates); $i < $c; $i++) {
                $line = $i === $c - 1 ? array_slice($text, $i) : (array) ($text[$i] ?? null);

                if ($coordinates[$i]->line === PHP_INT_MAX) {
                    $append[] = [$coordinates[$i], $line];
                } else {
                    $expanded[] = [$coordinates[$i], $line];
                }
            }
        }

        usort($expanded, static fn ($a, $b) => -$sort($a[0], $b[0]));

        return array_merge($expanded, array_reverse($append));
    }

    /**
     * @param list<array{list<Coordinate>, ?string}> $changes
     *
     * @return array<int, array{list<Coordinate>, ?string}>
     */
    protected function removeOverlaps(array $changes): array {
        $used = [];

        foreach ($changes as $key => [$coordinates]) {
            $lines = [];

            foreach ($coordinates as $coordinate) {
                $lines[$coordinate->line][] = $coordinate;

                if ($this->isOverlapped($used, $coordinate)) {
                    $lines = [];
                    break;
                }
            }

            if ($lines !== []) {
                foreach ($lines as $line => $coords) {
                    $used[$line] = array_merge($used[$line] ?? [], $coords);
                }
            } else {
                unset($changes[$key]);
            }
        }

        // Return
        return $changes;
    }

    /**
     * @param array<int, array<int, Coordinate>> $coordinates
     */
    private function isOverlapped(array $coordinates, Coordinate $coordinate): bool {
        // Append?
        if ($coordinate->line === PHP_INT_MAX) {
            return false;
        }

        // Check
        $overlapped = false;

        foreach ($coordinates[$coordinate->line] ?? [] as $c) {
            $aStart     = $c->offset;
            $aEnd       = $aStart + ($c->length ?? PHP_INT_MAX) - 1;
            $bStart     = $coordinate->offset;
            $bEnd       = $bStart + ($coordinate->length ?? PHP_INT_MAX) - 1;
            $overlapped = !($bEnd < $aStart || $bStart > $aEnd);

            if ($overlapped) {
                break;
            }
        }

        return $overlapped;
    }
}
