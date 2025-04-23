<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsappMessage;
use App\Models\Customer;
use Illuminate\Console\Command;
use Carbon\Carbon;

class BirthDayWish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthday:wish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send birthday wishes to customers with WhatsApp numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $today = Carbon::now()->format('m-d');

        $customers = Customer::whereNotNull('whatsapp')
            ->where('whatsapp', 'like', '971%')
            // ->whereRaw("TO_CHAR(date_of_birth, 'MM-DD') = ?", [$today])
            ->take(1)
            ->get(['first_name', 'last_name', 'whatsapp']);

        if (!count($customers)) {
            $this->info('No birthdays today.');
            return;
        }

        foreach ($customers as $customer) {

            SendWhatsappMessage::dispatch(
                $customer->whatsapp = "971554501483",
                $this->prepareMessage($customer->full_name)
            );
        }
    }

    function prepareMessage($name)
    {
        return "🎉 Happy Birthday, $name! 🎂\n\n"
            . "Wishing you a day filled with happiness, laughter, and all the things you love the most!\n"
            . "May this year bring you success, good health, and countless joyful moments.\n\n"
            . "Enjoy your special day! 🥳\n"
            . "Team RozeSkin";
    }
}
