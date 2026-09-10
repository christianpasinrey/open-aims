<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

/**
 * Canonical node keys. Two graphs that mention the same file, class,
 * function or resource build the same key, which is what merges them.
 *
 *   file      open-aims:app/Models/Issue.php
 *   class     open-aims:app/Models/Issue.php#Issue
 *   function  open-aims:app/Models/Issue.php#Issue::milestone  (or #fn without class)
 *   resource  open-aims:resource:column:issues.project_milestone_id
 */
final class NodeKey
{
    public static function forFile(string $repo, string $file): string
    {
        return $repo.':'.$file;
    }

    public static function forClass(string $repo, string $file, string $class): string
    {
        return self::forFile($repo, $file).'#'.$class;
    }

    public static function forFunction(string $repo, string $file, ?string $class, string $function): string
    {
        return self::forFile($repo, $file).'#'.($class !== null ? $class.'::'.$function : $function);
    }

    public static function forResource(string $repo, string $type, string $name): string
    {
        return $repo.':resource:'.$type.':'.$name;
    }

    public static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    /** A canonical key always carries the repo prefix; local ids never contain a colon. */
    public static function isCanonical(string $reference): bool
    {
        return str_contains($reference, ':');
    }
}
