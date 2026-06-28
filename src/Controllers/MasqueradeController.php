<?php

namespace AgedNerd\Masquerade\Controllers;

use AgedNerd\Masquerade\Services\MasqueradeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class MasqueradeController extends Controller
{
    public function take(Request $request, MasqueradeManager $manager, int|string $id, ?string $guardName = null): RedirectResponse
    {
        $guardName ??= $manager->getDefaultSessionGuard();
        $actor = $request->user($manager->getCurrentAuthGuardName());
        abort_unless($actor, 401);

        $subject = $manager->findUserById($id, $guardName);
        abort_if(
            $actor->getAuthIdentifier() == $subject->getAuthIdentifier()
            && $manager->getCurrentAuthGuardName() === $guardName,
            403,
        );
        abort_unless(method_exists($actor, 'canMasquerade') && $actor->canMasquerade($subject), 403);
        abort_unless(method_exists($subject, 'canBeMasqueraded') && $subject->canBeMasqueraded($actor), 403);

        $remember = $request->has('remember') ? $request->boolean('remember') : null;
        $manager->take($actor, $subject, $guardName, $remember);

        return $this->redirect($manager->getTakeRedirectTo($request->string('redirect_to')->toString() ?: null));
    }

    public function leave(Request $request, MasqueradeManager $manager): RedirectResponse
    {
        abort_unless($manager->isMasquerading(), 403);
        $manager->leave();

        return $this->redirect($manager->getLeaveRedirectTo($request->string('redirect_to')->toString() ?: null));
    }

    private function redirect(string $destination): RedirectResponse
    {
        return $destination === 'back' ? redirect()->back() : redirect()->to($destination);
    }
}
