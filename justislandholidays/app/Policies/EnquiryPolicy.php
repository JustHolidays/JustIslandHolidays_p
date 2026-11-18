<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    /** The recipient, or an admin for the listing's island (read-only, audited when opened). */
    public function view(User $user, Enquiry $enquiry): bool
    {
        return $this->isRecipient($user, $enquiry) || $this->adminCovers($user, $enquiry);
    }

    /** Only the recipient marks an enquiry new, replied or closed. */
    public function updateStatus(User $user, Enquiry $enquiry): bool
    {
        return $this->isRecipient($user, $enquiry);
    }

    public function markSpam(User $user, Enquiry $enquiry): bool
    {
        return $this->isRecipient($user, $enquiry) || $this->adminCovers($user, $enquiry);
    }

    private function isRecipient(User $user, Enquiry $enquiry): bool
    {
        return $enquiry->owner_id === $user->id;
    }

    private function adminCovers(User $user, Enquiry $enquiry): bool
    {
        return $user->isAdmin() && $user->managesIsland($enquiry->listing?->island_id);
    }
}
