<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Tests\Functional\Fixtures\Entity;

use Angle\TrailBundle\Attribute\TrailExclude;
use Doctrine\ORM\Mapping as ORM;

#[TrailExclude]
#[ORM\Entity]
#[ORM\Table(name: 'test_session_logs')]
class SessionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $data = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }
}
