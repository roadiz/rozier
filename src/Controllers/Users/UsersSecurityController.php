<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Users;

use RZ\Roadiz\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\UserSecurityType;
use Themes\Rozier\RozierApp;

/**
 * Provide user security views and forms.
 */
class UsersSecurityController extends RozierApp
{
    /**
     * @param Request $request
     * @param int     $userId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function securityAction(Request $request, int $userId)
    {
        // Only user managers can review security
        $this->denyAccessUnlessGranted('ROLE_ACCESS_USERS');
        /** @var User|null $user */
        $user = $this->em()->find(User::class, $userId);

        if ($user !== null) {
            $this->assignation['user'] = $user;
            $form = $this->createForm(UserSecurityType::class, $user, [
                'canChroot' => $this->isGranted("ROLE_SUPERADMIN")
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->em()->flush();

                $msg = $this->getTranslator()->trans(
                    'user.%name%.security.updated',
                    ['%name%' => $user->getUsername()]
                );

                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute(
                    'usersSecurityPage',
                    ['userId' => $user->getId()]
                );
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/users/security.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }
}
