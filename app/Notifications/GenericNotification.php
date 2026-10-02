<?php

namespace App\Notifications;

/** For simple account updates. $title and $body are Arabic source strings translated when rendered. */
class GenericNotification extends PlatformNotification
{
    public function __construct(
        private readonly string $titleText,
        private readonly string $bodyText,
        private readonly ?string $link = null,
    ) {
        parent::__construct();
    }

    protected function title(): string
    {
        return __($this->titleText);
    }

    protected function body(): string
    {
        return __($this->bodyText);
    }

    protected function url(): ?string
    {
        return $this->link;
    }
}
