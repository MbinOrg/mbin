<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

class PasswordResetFormTest extends TestCase
{
    public function testResetRequestRequiresAnEmail(): void
    {
        $form = $this->createFormFactory()->create(ResetPasswordRequestFormType::class);
        $form->submit(['email' => '']);

        $this->assertFalse($form->isValid());
        $this->assertSame('Please enter your email', $form->get('email')->getErrors()[0]->getMessage());
    }

    public function testPasswordChangeRequiresANewPassword(): void
    {
        $form = $this->createFormFactory()->create(ChangePasswordFormType::class);
        $form->submit(['plainPassword' => ['first' => '', 'second' => '']]);

        $this->assertFalse($form->isValid());
        $this->assertSame('Please enter a password', $form->get('plainPassword')->get('first')->getErrors()[0]->getMessage());
    }

    private function createFormFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }
}
