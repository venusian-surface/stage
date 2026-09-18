<?php

namespace Surface\Stage\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/**
 * @method static \Surface\Contracts\Stage\GPUStagedWindow open(string $name, \Surface\Contracts\Drawing\GPUEngine|string|null $engine, int $width, int $height, \Surface\Contracts\Stage\StageHost|string|null $host = null)
 * @method static \Surface\Contracts\Stage\CPUStagedWindow openCPU(string $name, \Surface\Contracts\Drawing\CPUEngine|string|null $engine, \Surface\Contracts\Drawing\CPUHost $canvas, int $width, int $height, \Surface\Contracts\Stage\StageHost|string|null $host = null, \Surface\Contracts\Stage\StageFit|string|null $fit = null)
 * @method static \Surface\Contracts\Stage\CPUStagedWindow emulate(string $name, \Surface\Contracts\Drawing\CPUHost $panel, int $zoom = 4, \Surface\Contracts\Drawing\CPUEngine|string|null $engine = null, \Surface\Contracts\Stage\StageHost|string|null $host = null)
 * @method static \Surface\Contracts\Stage\StagedWindow get(string $name)
 * @method static bool has(string $name)
 * @method static array all()
 * @method static void closeAll()
 * @method static void destroy()
 * @method static \Surface\Contracts\Stage\StageSession driver(string|null $host = null)
 *
 * @see \Surface\Stage\StageManager
 */
class Stage extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'stages';
    }
}
