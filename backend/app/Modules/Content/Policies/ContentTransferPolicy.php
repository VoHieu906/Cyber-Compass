<?php

namespace App\Modules\Content\Policies;

use App\Models\ContentTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Content package uploads and exports (F6) belong to the admin who started them; another admin's is a 404. */
final class ContentTransferPolicy
{
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, ContentTransfer $transfer): Response
    {
        return $this->own($actor, $transfer);
    }

    public function upload(User $actor, ContentTransfer $transfer): Response
    {
        return $this->own($actor, $transfer);
    }

    public function apply(User $actor, ContentTransfer $transfer): Response
    {
        return $this->own($actor, $transfer);
    }

    public function delete(User $actor, ContentTransfer $transfer): Response
    {
        return $this->own($actor, $transfer);
    }

    private function own(User $actor, ContentTransfer $transfer): Response
    {
        return $actor->isAdmin() && $transfer->user_id === $actor->id ? Response::allow() : Response::denyAsNotFound();
    }
}
