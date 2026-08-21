<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class ConfirmAction extends Component
{
    public bool $show = false;

    public string $message = '';

    public string $eventName = '';

    public array $eventParams = [];

    #[On('showConfirm')]
    public function open(string $message, string $eventName, array $eventParams): void
    {
        $this->message = $message;
        $this->eventName = $eventName;
        $this->eventParams = $eventParams;
        $this->show = true;
    }

    public function confirm(): void
    {
        if (count($this->eventParams) > 0) {
            $this->dispatch($this->eventName, $this->eventParams);
        } else {
            $this->dispatch($this->eventName);
        }
        $this->reset('show', 'message', 'eventName', 'eventParams');
    }

    public function cancel(): void
    {
        $this->reset('show', 'message', 'eventName', 'eventParams');
    }

    public function render()
    {
        return view('livewire.confirm-action');
    }
}
