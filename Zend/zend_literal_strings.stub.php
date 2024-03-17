<?php

/** @generate-class-entries */

/**
 * @strict-properties
 * @not-serializable
 */
final class LiteralString implements Stringable {
    private function __construct(string $string) {}
    public static function from(string $string): LiteralString {}
    public function __toString(): string {}
}