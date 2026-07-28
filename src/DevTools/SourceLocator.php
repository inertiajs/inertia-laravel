<?php

namespace Inertia\DevTools;

use Inertia\Controller;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * Resolves source-code locations for devtools: the caller frame of a share()/render()
 * call, the line a prop key is defined on, the share() method body, and a route
 * action's reflection. Pure lookup, no collector or request state.
 */
class SourceLocator
{
    /** @var array<string, array<int, string>|null> */
    protected array $fileCache = [];

    /**
     * Find the first caller frame outside of this package and vendor code.
     *
     * @return array{file: string, line: int}|null
     */
    public function captureCallerSource(): ?array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $srcDir = dirname(__DIR__);

        foreach ($backtrace as $frame) {
            if (($frame['class'] ?? null) === Controller::class && $frame['function'] === '__invoke') {
                return null;
            }

            if (! isset($frame['file'], $frame['line'])) {
                continue;
            }

            if (str_starts_with($frame['file'], $srcDir)) {
                continue;
            }

            if (str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            return ['file' => $frame['file'], 'line' => $frame['line']];
        }

        return null;
    }

    /**
     * Resolve the source location of a shared prop key by scanning the share() method.
     *
     * @return array{file: string, line: int}|null
     */
    public function resolveShareSource(ReflectionMethod $reflection, string $key): ?array
    {
        $file = $reflection->getDeclaringClass()->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (! $file || ! $startLine || ! $endLine) {
            return null;
        }

        $line = $this->findPropKeyLine($file, $startLine, $key, $endLine);

        if ($line !== null) {
            return ['file' => $file, 'line' => $line];
        }

        if (! $this->methodBodyContains($reflection, 'parent::share(')) {
            return null;
        }

        $parent = $reflection->getDeclaringClass()->getParentClass();

        if ($parent === false || ! $parent->hasMethod('share')) {
            return null;
        }

        return $this->resolveShareSource($parent->getMethod('share'), $key);
    }

    public function methodBodyContains(ReflectionMethod $reflection, string $needle): bool
    {
        $file = $reflection->getDeclaringClass()->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (! $file || ! $startLine || ! $endLine) {
            return false;
        }

        $lines = $this->readSourceLines($file);
        if ($lines === null) {
            return false;
        }

        for ($i = $startLine - 1; $i < min($endLine, count($lines)); $i++) {
            if (str_contains($lines[$i], $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the first plausible line of a share() method body, used as a fallback
     * when a specific prop key line cannot be found.
     *
     * @return array{file: string, line: int}|null
     */
    public function shareSourceFallback(ReflectionMethod $reflection): ?array
    {
        $file = $reflection->getDeclaringClass()->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (! $file || ! $startLine || ! $endLine) {
            return null;
        }

        $lines = $this->readSourceLines($file);
        if ($lines === null) {
            return null;
        }

        for ($i = $startLine - 1; $i < min($endLine, count($lines)); $i++) {
            if (str_contains($lines[$i], 'function') || str_contains($lines[$i], 'return [')) {
                return ['file' => $file, 'line' => $i + 1];
            }
        }

        return ['file' => $file, 'line' => $startLine];
    }

    /**
     * @return array<int, string>|null
     */
    public function readSourceLines(?string $file): ?array
    {
        if (! $file) {
            return null;
        }

        if (array_key_exists($file, $this->fileCache)) {
            return $this->fileCache[$file];
        }

        if (! is_file($file) || ! is_readable($file)) {
            return $this->fileCache[$file] = null;
        }

        $lines = file($file);

        return $this->fileCache[$file] = ($lines === false ? null : $lines);
    }

    /**
     * Find the source line where a prop key is defined in an array literal.
     */
    public function findPropKeyLine(string $file, int $startLine, string $key, ?int $endLine = null): ?int
    {
        $lines = $this->readSourceLines($file);

        if ($lines === null) {
            return null;
        }

        $maxScan = min($endLine ?? $startLine + 100, count($lines));
        $pattern = "/['\"]".preg_quote($key, '/')."['\"]\s*=>/";

        for ($i = $startLine - 1; $i < $maxScan; $i++) {
            if (preg_match($pattern, $lines[$i])) {
                return $i + 1;
            }
        }

        return null;
    }

    /**
     * Resolve the route action source location when possible.
     *
     * @return array{file: string, line: int}|null
     */
    public function resolveActionSource(?string $action, mixed $uses = null): ?array
    {
        if ($action === null) {
            return null;
        }

        try {
            $reflection = self::reflectAction($action, $uses);

            if ($reflection === null) {
                return null;
            }

            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();

            if ($file && $line) {
                return ['file' => $file, 'line' => $line];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Reflect a route action, covering closures, controller methods, array callables,
     * and invokable controllers.
     */
    protected static function reflectAction(string $action, mixed $uses): ?ReflectionFunctionAbstract
    {
        if ($uses instanceof \Closure) {
            return new \ReflectionFunction($uses);
        }

        if (is_array($uses) && count($uses) === 2) {
            return new ReflectionMethod($uses[0], $uses[1]);
        }

        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);

            return new ReflectionMethod($class, $method);
        }

        if (is_object($uses) && method_exists($uses, '__invoke')) {
            return new ReflectionMethod($uses, '__invoke');
        }

        if (class_exists($action) && method_exists($action, '__invoke')) {
            return new ReflectionMethod($action, '__invoke');
        }

        return null;
    }
}
