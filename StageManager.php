<?php

namespace Surface\Stage;

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\CPUStagedWindow;
use Surface\Contracts\Stage\GPUStagedWindow;
use Surface\Contracts\Stage\StagedWindow as StagedWindowContract;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageFit;
use Surface\Contracts\Stage\StageHost;
use Surface\Contracts\Stage\StageSession as StageSessionContract;
use Surface\Drawing\CPUEngineManager;
use Surface\Drawing\GPUEngineManager;
use Voyager\IOPools\IOPoolDock;
use Voyager\NutsAndBolts\Manager;

/**
 * Names a stage host and resolves the container alias its package bound; opens
 * stages on it with a named GPU engine and keeps the registry. Reads the
 * injected config repository, never the global helper. Missing package: the
 * container's own not-found — the same decision as the bridge and GPU seams.
 */
class StageManager extends Manager
{
    /** @var array<string, StagedWindowContract> GPU and CPU stages alike. */
    protected array $stages = [];

    public function getDefaultDriver(): string
    {
        return $this->config->get('stage.default', device_os_family() === 'mac' ? 'appkit' : 'sdl3');
    }

    /**
     * Open a stage — hidden; show() presents it.
     * @throws StageException When the name is taken or the host cannot give the engine its surface.
     */
    public function open(string $name, GPUEngine|string|null $engine, int $width, int $height, StageHost|string|null $host = null): GPUStagedWindow
    {
        if ($this->has($name)) {
            throw StageException::nameTaken($name);
        }

        /** @var StageSessionContract $session */
        $session = $this->driver($host instanceof StageHost ? $host->value : $host);
        $session->connect();

        $driver = $this->engines()->driver($engine instanceof GPUEngine ? $engine->value : $engine);
        $stage = $session->open($name, $driver, $width, $height);
        $stage->setPool($this->dock());
        $this->resourceFor($session)->track($stage);

        return $this->stages[$name] = $stage;
    }

    /**
     * Open a CPU stage — a window that presents $canvas, hidden until show().
     * The canvas keeps the size it was minted at; $width/$height are the
     * window and $fit says how the canvas is scaled into it (null reads
     * config('stage.cpu_fit')).
     *
     * @throws StageException When the name is taken or the host cannot present a canvas.
     */
    public function openCPU(string $name, CPUEngine|string|null $engine, CPUHost $canvas, int $width, int $height, StageHost|string|null $host = null, StageFit|string|null $fit = null): CPUStagedWindow
    {
        if ($this->has($name)) {
            throw StageException::nameTaken($name);
        }

        /** @var StageSessionContract $session */
        $session = $this->driver($host instanceof StageHost ? $host->value : $host);
        $session->connect();

        $driver = $this->cpuEngines()->driver($engine instanceof CPUEngine ? $engine->value : $engine);
        $stage = $session->openCPU($name, $driver, $canvas, $width, $height, $this->fit($fit));
        $stage->setPool($this->dock());
        $this->resourceFor($session)->track($stage);

        return $this->stages[$name] = $stage;
    }

    /**
     * A panel on screen: the canvas at whole-number zoom, nearest-neighbour,
     * so a 128x64 OLED sketch is eyeballable on a desktop before it meets the
     * hardware.
     *
     * @throws StageException When the name is taken, the zoom is below one, or the host cannot present a canvas.
     */
    public function emulate(string $name, CPUHost $panel, int $zoom = 4, CPUEngine|string|null $engine = null, StageHost|string|null $host = null): CPUStagedWindow
    {
        if ($zoom < 1) {
            throw new StageException("A stage zoom is a whole number of pixels, at least 1; got {$zoom}.");
        }

        return $this->openCPU($name, $engine, $panel, $panel->width * $zoom, $panel->height * $zoom, $host, StageFit::INTEGER_SCALE);
    }

    public function get(string $name): StagedWindowContract
    {
        return $this->all()[$name] ?? throw StageException::noSuchStage($name);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    /** @return array<string, StagedWindowContract> The stages still open. */
    public function all(): array
    {
        return $this->stages = array_filter($this->stages, fn (StagedWindowContract $stage) => $stage->isOpen());
    }

    public function closeAll(): void
    {
        foreach ($this->stages as $stage) {
            $stage->close();
        }

        $this->stages = [];
    }

    /**
     * Program teardown: close every stage, then disconnect every host session
     * this manager created. A failure does not spare the rest; the first one
     * is rethrown once everything was tried.
     */
    public function destroy(): void
    {
        $failure = null;

        foreach ($this->stages as $stage) {
            try {
                $stage->close();
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        $this->stages = [];

        /** @var StageSessionContract $session */
        foreach ($this->drivers as $session) {
            try {
                $session->disconnect();
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        if (! is_null($failure)) {
            throw $failure;
        }
    }

    protected function createAppkitDriver(): StageSessionContract
    {
        return $this->resolveAlias('appkit', 'stage.appkit');
    }

    protected function createSdl3Driver(): StageSessionContract
    {
        return $this->resolveAlias('sdl3', 'stage.sdl3');
    }

    protected function createGlfwDriver(): StageSessionContract
    {
        return $this->resolveAlias('glfw', 'stage.glfw');
    }

    protected function resolveAlias(string $host, string $default): StageSessionContract
    {
        return $this->vessel->get($this->config->get("stage.hosts.{$host}.alias", $default));
    }

    protected function resourceFor(StageSessionContract $session): StageResourceDriver
    {
        $key = 'stage.'.$session->host()->value;
        $dock = $this->dock();
        $resource = $dock->resources()->get($key);

        if ($resource instanceof StageResourceDriver) {
            return $resource;
        }

        $resource = new StageResourceDriver($dock, $session);
        $dock->resource($key, $resource);

        return $resource;
    }

    protected function dock(): IOPoolDock
    {
        return $this->vessel->make('io-pool');
    }

    protected function engines(): GPUEngineManager
    {
        return $this->vessel->get('gpu-engines');
    }

    protected function fit(StageFit|string|null $fit): StageFit
    {
        if ($fit instanceof StageFit) {
            return $fit;
        }

        return StageFit::from($fit ?? $this->config->get('stage.cpu_fit', StageFit::INTEGER_SCALE->value));
    }

    protected function cpuEngines(): CPUEngineManager
    {
        return $this->vessel->get('cpu-engines');
    }
}
