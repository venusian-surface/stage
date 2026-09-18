<?php

namespace Surface\Stage;

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\Stage\CPUStagedWindow as CPUStagedWindowContract;
use Surface\Contracts\Stage\StageFit;

/**
 * A stage that presents a CPU canvas. The canvas owns the hook, the clock and
 * the pixels; this class owns the window and one verb — applyPresent(), handed
 * the canvas's RGBA8 whenever the window should show it again. The canvas keeps
 * the size it was minted at; a window resize only changes how it is scaled.
 */
abstract class CPUStagedWindow extends AbstractStage implements CPUStagedWindowContract
{
    public function __construct(
        string $name,
        protected CPUDrawTarget $canvas,
        int $width,
        int $height,
        float $scale = 1.0,
        protected StageFit $fit = StageFit::INTEGER_SCALE,
    ) {
        parent::__construct($name, $width, $height, $scale);
    }

    public function canvas(): CPUDrawTarget
    {
        return $this->canvas;
    }

    public function fit(): StageFit
    {
        return $this->fit;
    }

    public function canvasSize(): array
    {
        return $this->canvas->drawableSize();
    }

    public function engine(): CPUEngine
    {
        return $this->canvas->engine();
    }

    public function hostFormat(): FormatSpec
    {
        return $this->canvas->hostFormat();
    }

    public function drawing(): Drawing2D
    {
        return $this->canvas->drawing();
    }

    public function drawableSize(): array
    {
        return $this->canvas->drawableSize();
    }

    public function onDraw(callable $hook): static
    {
        $this->canvas->onDraw($hook);

        return $this;
    }

    public function setClearColor(Color $color): static
    {
        $this->canvas->setClearColor($color);

        return $this;
    }

    public function setContinuous(bool $continuous): static
    {
        $this->canvas->setContinuous($continuous);

        return $this;
    }

    public function redraw(): static
    {
        $this->canvas->redraw();

        return $this;
    }

    /** One canvas frame, then the pixels to the window. Skipped while hidden. */
    public function renderFrame(): bool
    {
        if (! $this->visible()) {
            return false;
        }

        if (! $this->canvas->renderFrame()) {
            return false;
        }

        $this->present();

        return true;
    }

    public function flush(?FormatSpec $spec = null, bool $as_array = false): string|array
    {
        return $this->canvas->flush($spec, $as_array);
    }

    public function flushRegion(Region $region, ?FormatSpec $spec = null, bool $as_array = false): string|array
    {
        return $this->canvas->flushRegion($region, $spec, $as_array);
    }

    public function damage(): array
    {
        return $this->canvas->damage();
    }

    public function rgba8(): string
    {
        return $this->canvas->rgba8();
    }

    /** Hand the window what the canvas holds now. No hook runs. */
    protected function present(): void
    {
        if ($this->visible()) {
            $this->applyPresent($this->canvas->rgba8());
        }
    }

    protected function presented(): void
    {
        $this->present();
    }

    /** The window is a different size; the canvas is not. Show it again at the new scale. */
    protected function applyResize(int $width, int $height, float $scale): void
    {
        $this->present();
    }

    /** RGBA8 at canvas size, top-left first. Called per presented frame, on show, and on resize. */
    abstract protected function applyPresent(string $rgba8): void;
}
