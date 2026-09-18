<?php

namespace Surface\Stage;

use Surface\Contracts\Stage\Events\StageClosed;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StagedWindow as StagedWindowContract;
use Surface\Contracts\Stage\StageException;
use Voyager\Contracts\IOPools\PoolPump;

/**
 * Shared policy for every engine-owned window, GPU or CPU: the size and scale
 * Surface believes in, change-only resize mail, one close announcement, and
 * release-before-destroy. Minted hidden: nothing renders until show(). Host
 * packages fill applyTitle / applyShow / destroyNative and call the two doors
 * (resized, closeRequested) from their pump; the kind classes fill
 * applyResize / releaseEngine / presented.
 */
abstract class AbstractStage implements StagedWindowContract
{
    protected string $title = '';

    protected bool $open = true;

    protected bool $close_announced = false;

    protected bool $shown = false;

    protected ?PoolPump $io_pool = null;

    public function __construct(
        public readonly string $name,
        protected int $width,
        protected int $height,
        protected float $scale = 1.0,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->guardOpen();
        $this->title = $title;
        $this->applyTitle($title);

        return $this;
    }

    public function size(): array
    {
        return [$this->width, $this->height];
    }

    public function scale(): float
    {
        return $this->scale;
    }

    public function show(): static
    {
        $this->guardOpen();
        $this->shown = true;
        $this->applyShow();
        $this->presented();

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function setPool(PoolPump $pool): static
    {
        $this->io_pool = $pool;

        return $this;
    }

    /**
     * Door for the host's pump: the window now measures this many points at
     * this backing scale. Change-only; the kind reacts through applyResize()
     * and the sketch hears about it once.
     */
    public function resized(int $width, int $height, float $scale): void
    {
        if (! $this->open) {
            return;
        }

        if ($width === $this->width && $height === $this->height && $scale === $this->scale) {
            return;
        }

        $this->width = $width;
        $this->height = $height;
        $this->scale = $scale;
        $this->applyResize($width, $height, $scale);
        $this->io_pool?->push(new StageResized($this->name, $width, $height, $scale));
    }

    /**
     * Door for the host's pump: the user asked to close this stage. Mails
     * once; the window stays — the sketch decides whether to close(). With no
     * pool yet nothing is mailed and nothing is spent.
     */
    public function closeRequested(): void
    {
        if ($this->close_announced || is_null($this->io_pool)) {
            return;
        }

        $this->close_announced = true;
        $this->io_pool->push(new StageClosed($this->name));
    }

    /**
     * Terminal: the engine's resources first, then the native, then the one
     * announcement. A release that throws still destroys the native and
     * announces; its exception then propagates.
     */
    public function close(): void
    {
        if (! $this->open) {
            return;
        }

        $this->open = false;

        try {
            $this->releaseEngine();
        } finally {
            $this->destroyNative();
            $this->closeRequested();
        }
    }

    /** Open and shown — the one visibility rule both kinds use. */
    protected function visible(): bool
    {
        return $this->open && $this->shown;
    }

    protected function guardOpen(): void
    {
        if (! $this->open) {
            throw StageException::closed($this->name);
        }
    }

    /** What this kind holds from an engine. Called inside close(), before the native goes. */
    protected function releaseEngine(): void {}

    /** The window changed size; the kind decides what that means for its pixels. */
    protected function applyResize(int $width, int $height, float $scale): void {}

    /** Just shown: a kind that presents its own pixels paints here. */
    protected function presented(): void {}

    abstract protected function applyTitle(string $title): void;

    abstract protected function applyShow(): void;

    /** Destroy the native window. The engine is already released. Terminal. */
    abstract protected function destroyNative(): void;
}
