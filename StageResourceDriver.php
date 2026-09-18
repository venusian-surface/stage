<?php

namespace Surface\Stage;

use Surface\Contracts\Stage\StagedWindow as StagedWindowContract;
use Surface\Contracts\Stage\StageSession as StageSessionContract;
use Voyager\Contracts\IOPools\IOResourceDriver;
use Voyager\IOPools\IOPoolDock;

/**
 * The dock resource for one stage host: pump the host, then run one frame on
 * each of its open stages. A host that shares the native bridge's pump
 * (AppKit) is not pumped again while the 'os' resource is on the dock — that
 * resource already drained NSApp this tick. A host that owns the native pump
 * (SDL on macOS) drains it instead, spending the idle wait the 'os' resource
 * hands it; otherwise the pump never waits.
 */
final class StageResourceDriver implements IOResourceDriver
{
    /** @var array<string, StagedWindowContract> */
    protected array $stages = [];

    /** Idle wait handed over by the os resource when this host owns the native pump; spent on the next tick. */
    protected int $wait_budget_ms = 0;

    public function __construct(
        protected IOPoolDock $dock,
        public readonly StageSessionContract $session,
    ) {}

    public function track(StagedWindowContract $stage): void
    {
        $this->stages[$stage->name()] = $stage;
    }

    /** @return array<string, StagedWindowContract> */
    public function stages(): array
    {
        return $this->stages;
    }

    /** True while this host must drain the OS event queue in place of the os resource. */
    public function ownsNativePump(): bool
    {
        return $this->session->connected() && $this->session->ownsNativePump();
    }

    public function waitBudget(int $ms): static
    {
        $this->wait_budget_ms = max(0, $ms);

        return $this;
    }

    public function tick(): void
    {
        if ($this->ownsNativePump()) {
            $this->session->pump($this->wait_budget_ms);
            $this->wait_budget_ms = 0;
        } elseif (! ($this->session->sharesNativePump() && ! is_null($this->dock->os()))) {
            $this->session->pump(0);
        }

        foreach ($this->stages as $name => $stage) {
            if (! $stage->isOpen()) {
                unset($this->stages[$name]);

                continue;
            }

            $stage->renderFrame();
        }
    }
}
