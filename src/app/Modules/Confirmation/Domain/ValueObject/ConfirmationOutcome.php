<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\ValueObject;

/**
 * Зеркало строкового статуса в общей таблице `incoming_transactions`.
 * Phase 4 считал переходы (detected → confirming → confirmed).
 * Phase 5 расширяет: finalized — когда tx ушла глубже max_reorg_depth и больше
 * не реактивна к обычному confirmation tick'у; orphaned — выпала из канона
 * после reorg (ставится ReorgDetection-модулем, Confirmation её не трогает).
 *
 * Живёт в этом модуле, чтобы Confirmation::Domain не импортировал чужие неймспейсы.
 */
enum ConfirmationOutcome: string
{
    case Detected = 'detected';
    case Confirming = 'confirming';
    case Confirmed = 'confirmed';
    case Finalized = 'finalized';
    case Orphaned = 'orphaned';
}
