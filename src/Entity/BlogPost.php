<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'forum_posts')]
class BlogPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(name: 'image_path', length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    private ?string $excerpt = null;

    private ?string $category = 'Forum';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self { $this->author = $author; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getExcerpt(): ?string
    {
        if ($this->excerpt !== null && trim($this->excerpt) !== '') {
            return $this->excerpt;
        }

        $text = trim(strip_tags($this->content ?? ''));
        if ($text === '') {
            return '';
        }

        if (\function_exists('mb_strlen') && \function_exists('mb_substr')) {
            if (mb_strlen($text) <= 160) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, 157)).'...';
        }

        if (strlen($text) <= 160) {
            return $text;
        }

        return rtrim(substr($text, 0, 157)).'...';
    }

    public function setExcerpt(?string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category ?: 'Forum';

        return $this;
    }

    // Compatibility getters for existing Twig templates
    public function getFeaturedImage(): ?string { return $this->imagePath; }
    public function setFeaturedImage(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }
    public function getCategory(): string { return $this->category ?: 'Forum'; }
}
