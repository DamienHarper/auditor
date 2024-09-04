<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
#[ORM\Table(name: 'post')]
#[ORM\Index(name: 'fk_author_id', columns: ['author_id'])]
#[Gedmo\SoftDeleteable(fieldName: 'deleted_at', timeAware: false, hardDelete: false)]
class Post implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    protected ?string $body = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    protected ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $deleted_at = null;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true], nullable: true)]
    protected ?int $author_id = null;

    #[ORM\OneToMany(targetEntity: 'Comment', mappedBy: 'post', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'post_id')]
    protected Collection $comments;

    #[ORM\ManyToOne(targetEntity: 'Author', inversedBy: 'posts', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'author_id')]
    protected ?Author $author = null;

    #[ORM\ManyToOne(targetEntity: 'Author', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'coauthor_id')]
    protected ?Author $coauthor = null;

    #[ORM\ManyToMany(targetEntity: 'Tag', inversedBy: 'posts', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'post__tag')]
    #[ORM\JoinColumn(name: 'post_id', nullable: false)]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', nullable: false)]
    protected Collection $tags;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function __sleep()
    {
        return ['id', 'title', 'body', 'created_at', 'author_id'];
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setDeletedAt(?\DateTimeImmutable $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deleted_at;
    }

    public function setAuthorId(int $author_id): self
    {
        $this->author_id = $author_id;

        return $this;
    }

    public function getAuthorId(): ?int
    {
        return $this->author_id;
    }

    public function addComment(Comment $comment): self
    {
        $this->comments[] = $comment;

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        $this->comments->removeElement($comment);
        $comment->setPost(null);

        return $this;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function setCoauthor(?Author $author): self
    {
        $this->coauthor = $author;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function getCoauthor(): ?Author
    {
        return $this->coauthor;
    }

    public function addTag(Tag $tag): self
    {
        $tag->addPost($this);
        $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $tag->removePost($this);
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }
}
