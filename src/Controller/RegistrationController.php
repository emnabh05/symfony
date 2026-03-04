<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\RecaptchaVerifier;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormErrorIterator;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        Security $security,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        RecaptchaVerifier $recaptchaVerifier
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            /** @var FormErrorIterator $formErrors */
            $formErrors = $form->getErrors(true, true);
            foreach ($formErrors as $error) {
                $origin = $error->getOrigin();
                $name = $origin ? $origin->getName() : 'form';
                $errors[] = $name.': '.$error->getMessage();
            }

            if ($errors) {
                $this->addFlash('error', implode(' | ', $errors));
            } else {
                $this->addFlash('error', 'Form is invalid but no errors were returned.');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $recaptchaToken = (string) $request->request->get('g-recaptcha-response', '');
            if (
                $recaptchaVerifier->isConfigured()
                && $recaptchaToken !== ''
                && !$recaptchaVerifier->verify($recaptchaToken, $request->getClientIp())
            ) {
                $this->addFlash('error', 'Captcha invalide. Veuillez confirmer "Je ne suis pas un robot".');
                return $this->redirectToRoute('app_register');
            }

            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                if (!$this->isAllowedAvatarExtension($avatarFile)) {
                    $this->addFlash('error', 'Invalid image format. Allowed: jpg, jpeg, png, webp, gif.');
                    return $this->redirectToRoute('app_register');
                }
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$this->resolveAvatarExtension($avatarFile);

                try {
                    $avatarFile->move(
                        $this->getParameter('app.avatar_upload_dir'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload avatar. Please try again.');
                }

                $user->setAvatar($newFilename);
            }

$user->setPassword(
    $hasher->hashPassword(
        $user,
        $form->get('plainPassword')->getData()
    )
);

$selectedRole = $form->get('role')->getData() ?? 'ROLE_PATIENT';
$user->setRoles([$selectedRole]);

$em->persist($user);
$em->flush();

            return $security->login($user, AppAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'recaptcha_site_key' => (string) $this->getParameter('app.recaptcha_site_key'),
        ]);
    }

    private function isAllowedAvatarExtension(UploadedFile $file): bool
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function resolveAvatarExtension(UploadedFile $file): string
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $ext : 'jpg';
    }

}
