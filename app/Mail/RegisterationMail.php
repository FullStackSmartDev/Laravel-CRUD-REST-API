<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $status='';
    public $fullName='';

    public function __construct($status,$name)
    {
      $this->status = $status;
      $this->fullName = $name;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if($this->status=='approved'){
            return $this->view('mail.approved-account')
            ->from(env('MAIL_FROM_ADDRESS'))
            ->subject('Process BiraMedia Account')
            ->with(
                [
                      'name' => $this->fullName,
                ]);
        }
        else{
            return $this->view('mail.rejected-account')
            ->from(env('MAIL_FROM_ADDRESS'))
            ->subject('Process BiraMedia Account')
            ->with(
                [
                      'name' => $this->fullName,
                ]);
        }
    }
}
