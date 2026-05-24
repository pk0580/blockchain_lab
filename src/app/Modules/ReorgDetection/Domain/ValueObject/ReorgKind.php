<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\ValueObject;

/**
 * Результат сопоставления нового блока со stored-цепочкой.
 *
 *   NoBaseline       — сохранённого предыдущего блока нет (например, первый
 *                      просканированный блок после init курсора). Это нормально.
 *   CleanExtension   — stored prev совпадает с parent_hash нового блока, цепь
 *                      просто удлиняется.
 *   Reorg            — stored prev есть, но его hash не совпадает с parent_hash.
 *                      Кандидат на компенсацию: orphan stored prev, rollback курсор.
 */
enum ReorgKind: string
{
    case NoBaseline = 'no_baseline';
    case CleanExtension = 'clean_extension';
    case Reorg = 'reorg';
}
