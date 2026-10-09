<?php

declare(strict_types=1);

namespace Hwkdo\LlamaParseLaravel\Enums;

enum LlamaParseTier: string
{
    case Fast = 'fast';
    case CostEffective = 'cost_effective';
    case Agentic = 'agentic';
    case AgenticPlus = 'agentic_plus';
}
