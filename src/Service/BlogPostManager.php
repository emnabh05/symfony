<?php

namespace App\Service;

use App\Entity\BlogPost;

class BlogPostManager
{
    /** @var array<int, string> */
    private const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    public function validate(BlogPost $post): bool
    {
        $title = trim($post->getTitle());
        if (mb_strlen($title) < 3) {
            throw new \InvalidArgumentException('Le titre du blog doit contenir au moins 3 caracteres.');
        }

        $slug = trim($post->getSlug());
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('Le slug du blog est invalide.');
        }

        $status = trim($post->getStatus());
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException('Le statut du blog est invalide.');
        }

        return true;
    }
}

