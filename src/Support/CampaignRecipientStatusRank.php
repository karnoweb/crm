<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Karnoweb\Crm\Enums\CampaignRecipientStatus;

final class CampaignRecipientStatusRank
{
    public function shouldAdvance(CampaignRecipientStatus $current, CampaignRecipientStatus $next): bool
    {
        if ($current->isFailedTerminal()) {
            return false;
        }

        if ($next === CampaignRecipientStatus::Failed) {
            return $current->canFail();
        }

        return $next->rank() > $current->rank();
    }
}
