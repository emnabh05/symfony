<?php

namespace App\Tests\Service;

use App\Entity\BlogPost;
use App\Service\BlogPostManager;
use PHPUnit\Framework\TestCase;

class BlogPostManagerTest extends TestCase
{
    public function testValidBlogPost(): void
    {
        $manager = new BlogPostManager();
        $post = (new BlogPost())
            ->setTitle('Programme nutrition hebdomadaire')
            ->setSlug('programme-nutrition-hebdomadaire')
            ->setContent('Contenu blog de test')
            ->setStatus('draft')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        self::assertTrue($manager->validate($post));
    }

    public function testBlogPostWithInvalidSlug(): void
    {
        $manager = new BlogPostManager();
        $post = (new BlogPost())
            ->setTitle('Blog Fitness')
            ->setSlug('Blog Fitness !')
            ->setContent('Contenu blog de test')
            ->setStatus('published')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le slug du blog est invalide.');

        $manager->validate($post);
    }

    public function testBlogPostWithInvalidStatus(): void
    {
        $manager = new BlogPostManager();
        $post = (new BlogPost())
            ->setTitle('Blog Cardio')
            ->setSlug('blog-cardio')
            ->setContent('Contenu blog de test')
            ->setStatus('pending')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut du blog est invalide.');

        $manager->validate($post);
    }
}

