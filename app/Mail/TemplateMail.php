<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $renderedBody;
    public string $renderedSubject;

    /**
     * Create a new message instance.
     *
     * @param string $templateSlug  The email template slug (e.g. 'video-published')
     * @param array  $data          Placeholder key-value pairs (without {{ }})
     * @param string|null $replyTo  Optional reply-to address
     */
    public function __construct(
        protected string $templateSlug,
        protected array $data = [],
        protected ?string $replyToAddress = null,
        protected ?string $replyToName = null,
    ) {
        $template = EmailTemplate::findBySlug($this->templateSlug);

        if (!$template) {
            // Fallback: use defaults from the model if DB template missing
            $defaults = collect(EmailTemplate::defaults())->firstWhere('slug', $this->templateSlug);
            if ($defaults) {
                $template = new EmailTemplate($defaults);
            }
        }

        if ($template) {
            $this->renderedSubject = $template->renderSubject($this->data);
            $this->renderedBody = $template->renderBody($this->data);
        } else {
            $this->renderedSubject = $this->data['subject'] ?? config('app.name');
            $this->renderedBody = $this->data['body'] ?? '';
        }
    }

    public function build(): self
    {
        $mail = $this->subject($this->renderedSubject)
            ->view('emails.layout', [
                'body' => $this->renderedBody,
                'subject' => $this->renderedSubject,
            ]);

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress, $this->replyToName);
        }

        return $mail;
    }
}
