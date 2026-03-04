<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'forum_thread')]
class ForumThread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', columnDefinition: 'LONGTEXT')]
    private string $content;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(columnDefinition: "ENUM('open','closed','pinned')")]
    private string $status = 'open';

    #[ORM\Column(options: ['default' => false])]
    private bool $isSolved = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $replyCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastActivity = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = [];

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): self { $this->category = $category; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self { $this->author = $author; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function isSolved(): bool { return $this->isSolved; }
    public function setIsSolved(bool $isSolved): self { $this->isSolved = $isSolved; return $this; }

    public function getViewCount(): int { return $this->viewCount; }
    public function setViewCount(int $viewCount): self { $this->viewCount = $viewCount; return $this; }

    public function getReplyCount(): int { return $this->replyCount; }
    public function setReplyCount(int $replyCount): self { $this->replyCount = $replyCount; return $this; }

    public function getLastActivity(): ?\DateTimeImmutable { return $this->lastActivity; }
    public function setLastActivity(?\DateTimeImmutable $lastActivity): self { $this->lastActivity = $lastActivity; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $tags): self { $this->tags = $tags; return $this; }
}
