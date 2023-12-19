<?php

declare(strict_types=1);

namespace DH\Auditor\Tests\Provider\Doctrine\Fixtures\Entity\Standard\Blog;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="`comment`", indexes={@ORM\Index(name="fk_post_id", columns={"post_id"})})
 */
#[ORM\Entity]
#[ORM\Table(name: '`comment`')]
#[ORM\Index(name: 'fk__idx', columns: ['post_id'])]
class Comment
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="integer", options={"unsigned": true})
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    protected $id;

    /**
     * @ORM\Column(type="text")
     */
    #[ORM\Column(type: 'text')]
    protected $body;

    /**
     * Comment author email.
     *
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: 'string', length: 255)]
    protected $author;

    /**
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: 'datetime')]
    protected $created_at;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true}, nullable=true)
     */
    #[ORM\Column(type: 'integer', options: ['unsigned' => true], nullable: true)]
    protected $post_id;

    /**
     * @ORM\ManyToOne(targetEntity="Post", inversedBy="comments", cascade={"persist", "remove"})
     *
     * @ORM\JoinColumn(name="post_id", referencedColumnName="id", nullable=true)
     */
    #[ORM\ManyToOne(targetEntity: 'Post', inversedBy: 'comments', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true)]
    protected $post;

    public function __construct() {}

    public function __sleep()
    {
        return ['id', 'body', 'author', 'created_at', 'post_id'];
    }

    /**
     * Set the value of id.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of id.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the value of body.
     */
    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get the value of body.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Set the value of author.
     */
    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get the value of author.
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * Set the value of created_at.
     */
    public function setCreatedAt(?DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * Get the value of created_at.
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    /**
     * Set the value of post_id.
     */
    public function setPostId(int $post_id): self
    {
        $this->post_id = $post_id;

        return $this;
    }

    /**
     * Get the value of post_id.
     */
    public function getPostId(): ?int
    {
        return $this->post_id;
    }

    /**
     * Set Post entity (many to one).
     */
    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }

    /**
     * Get Post entity (many to one).
     */
    public function getPost(): ?Post
    {
        return $this->post;
    }
}
