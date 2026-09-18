<?php

namespace Surface\Stage;

use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\GPUStagedWindow as StagedWindowContract;
use Surface\Drawing\Concerns\RunsFrames;

/**
 * A stage a GPU engine draws through an Executor: AbstractStage plus the frame
 * loop (RunsFrames). Host packages fill the three native hooks; the executor
 * is resized on every window change and released before the native goes.
 */
abstract class StagedWindow extends AbstractStage implements StagedWindowContract
{
    use RunsFrames;

    public function __construct(
        string $name,
        protected GPUEngine $gpu_engine,
        protected Executor $executor,
        int $width,
        int $height,
        float $scale = 1.0,
    ) {
        parent::__construct($name, $width, $height, $scale);
        $this->bootFrames($executor);
    }

    public function engine(): GPUEngine
    {
        return $this->gpu_engine;
    }

    public function executor(): Executor
    {
        return $this->executor;
    }

    public function drawableSize(): array
    {
        return $this->executor->drawableSize();
    }

    /** Pixels, not points; an on-demand stage would otherwise show stale content. */
    protected function applyResize(int $width, int $height, float $scale): void
    {
        $this->executor->resize((int) round($width * $scale), (int) round($height * $scale));
        $this->requestFrame();
    }

    protected function releaseEngine(): void
    {
        $this->executor->release();
    }

    protected function frameSize(): array
    {
        return [$this->width, $this->height];
    }

    protected function frameScale(): float
    {
        return $this->scale;
    }

    protected function frameVisible(): bool
    {
        return $this->visible();
    }
}
