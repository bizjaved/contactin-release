<?php
namespace ContactInbox\Core\Traits;

use ContactInbox\Core\Repositories\SubmissionRepository;

if (!defined('ABSPATH')) exit;
trait SubmissionRateLimiterTrait {
    protected function getSubmissionRepo(): SubmissionRepository {
        return new SubmissionRepository();
    }

    protected function isRapidRepeat($data): bool {
        return $this->getSubmissionRepo()->countIdentical($data, 30) > 0;
    }

    protected function isShortTermRepeat($data, $max = 2): bool {
        return $this->getSubmissionRepo()->countIdentical($data, 300) >= $max;
    }

    protected function isLongTermRepeat($data, $max = 5): bool {
        return $this->getSubmissionRepo()->countIdentical($data, 86400) >= $max;
    }

    protected function isAbsoluteRepeat($data, $max = 10): bool {
        return $this->getSubmissionRepo()->countIdentical($data, 604800) >= $max;
    }
}
