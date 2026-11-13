<?php

namespace App\Actions;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Notifications\AccessGranted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Approve or decline someone who asked to send print requests; used by Share & export and the API.
 */
class HandleAccessRequest
{
    /**
     * Add the person to the owner's requester list and email them that they can request prints now.
     */
    public function approve(AccessRequest $accessRequest): AccessRequest
    {
        $this->ensurePending($accessRequest);

        $accessRequest->user->allowPrintRequester($accessRequest->email);
        $accessRequest->forceFill(['status' => AccessRequestStatus::Approved])->save();
        Notification::route('mail', [$accessRequest->email => $accessRequest->name])->notify(new AccessGranted($accessRequest));

        return $accessRequest;
    }

    /**
     * Decline quietly: the person sees on the share link that the owner does not take their requests.
     */
    public function decline(AccessRequest $accessRequest): AccessRequest
    {
        $this->ensurePending($accessRequest);

        $accessRequest->forceFill(['status' => AccessRequestStatus::Declined])->save();

        return $accessRequest;
    }

    private function ensurePending(AccessRequest $accessRequest): void
    {
        if ($accessRequest->status !== AccessRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This access request was already handled.']);
        }
    }
}
