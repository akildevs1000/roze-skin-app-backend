<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Mail\ActionMail;
use App\Mail\ActionMarkdownMail;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Template;
use App\Models\WhatsappClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function FilterCompanyList($model, $request, $model_name = null)
    {
        $model = $model::query();

        if (is_null($model_name)) {
            $model->when($request->company_id > 0, function ($q) use ($request) {
                return $q->where('company_id', $request->company_id);
            });

            $model->when(!$request->company_id, function ($q) use ($request) {
                return $q->where('company_id', 0);
            });
        }

        return $model;
    }

    public static function process($action, $job, $model, $id = null)
    {
        try {
            $m       = '\\App\\Models\\' . $model;
            $last_id = gettype($job) == 'object' ? $job->id : $id;

            $response = [
                'status'  => true,
                'record'  => $m::find($last_id),
                'message' => $model . ' has been ' . $action,
            ];

            if ($last_id) {
                return response()->json($response, 200);
            } else {
                return response()->json([
                    'status'  => false,
                    'record'  => null,
                    'message' => $model . ' cannot ' . $action,
                ], 200);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function response($msg, $record, $status)
    {
        return response()->json(['record' => $record, 'message' => $msg, 'status' => $status], 200);
    }

    public function process_search($model, $input, $fields = [])
    {
        $model->where('id', 'LIKE', "%$input%");

        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $model->orWhere($value, 'LIKE', "%$input%");
            } else {
                foreach ($value as $relation_value) {
                    $model->orWhereHas($key, function ($query) use ($input, $relation_value) {
                        $query->where($relation_value, 'like', '%' . $input . '%');
                    });
                }
            }
        }
        return $model;
    }

    public function getStatusText($status)
    {
        $report_type = "Summary";

        if ($status == 'P') {
            $report_type = "Present";
        } else if ($status == 'A') {
            $report_type = "Absent";
        } else if ($status == '---') {
            $report_type = "Missing";
        } else if ($status == 'ME') {
            $report_type = "Manual Entry";
        }

        return $report_type;
    }

    public function getNumFormat($Num = null)
    {
        if (!$Num) {
            return "---";
        }

        return number_format($Num, 2);
    }

    public function getBookingModel($bookingId)
    {
        return Booking::find($bookingId);
    }

    public function TransactionsCounts($request)
    {

        $expense = Expense::query()
            ->where('company_id', $request->company_id)
            ->orderByDesc("id");

        $income = Payment::query()
            ->where('company_id', $request->company_id)
            ->whereHas('booking', function ($q) {
                $q->where('booking_status', '!=', -1);
            })
            ->orderByDesc("id");

        if ($request->is_management == 1 && $request->has('is_management') && $request->filled('is_management')) {
            $expense->where('is_management', 1);
        } else {
            $expense->where('is_management', 0);
        }

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->from;
            $to   = $request->to;
            $expense->whereDate('created_at', '>=', $from);
            $expense->whereDate('created_at', '<=', $to);

            $income->whereDate('created_at', '>=', $from);
            $income->whereDate('created_at', '<=', $to);
        }

        $incomingWithoutCityLedger = $income->clone()->sum('amount') - $this->getSum($income, 7);
        $loss                      = $incomingWithoutCityLedger - $income->clone()->sum('amount');
        $profit                    = $incomingWithoutCityLedger - $expense->clone()->sum('amount');

        return [
            'expense' => [
                'Cash'         => $expense->clone()->whereHas('paymentMode', fn($q) => $q->where('id', 1))->sum('total'),
                'Card'         => $expense->clone()->whereHas('paymentMode', fn($q) => $q->where('id', 2))->sum('total'),
                'Online'       => $expense->clone()->whereHas('paymentMode', fn($q) => $q->where('id', 3))->sum('total'),
                'Bank'         => $expense->clone()->whereHas('paymentMode', fn($q) => $q->where('id', 4))->sum('total'),
                'UPI'          => $expense->clone()->whereHas('paymentMode', fn($q) => $q->where('id', 5))->sum('total'),
                'Cheque'       => $expense->clone()->whereHas('paymentMode', fn($q) => $q->where('id', 6))->sum('total'),
                'OverallTotal' => $expense->clone()->sum('total'),
            ],
            'income'  => [
                'Cash'         => $this->getSum($income, 1),
                'Card'         => $this->getSum($income, 2),
                'Online'       => $this->getSum($income, 3),
                'Bank'         => $this->getSum($income, 4),
                'UPI'          => $this->getSum($income, 5),
                'Cheque'       => $this->getSum($income, 6),
                'City_ledger'  => $this->getSum($income, 7),
                'OverallTotal' => $incomingWithoutCityLedger,
            ],

            'profit'  => $profit > 0 ? $profit : 0 . '.00',
            'loss'    => $loss > 0 ? $loss . '.00' : 0 . '.00',

            // 'profit' =>  $profit > 0 ? $profit . '.00' : 0 . '.00',
            // 'loss' =>  $loss > 0 ? $loss . '.00' : 0 . '.00',
        ];
    }

    public function getSum($model, $id)
    {
        return $model->clone()->whereHas('paymentMode', fn($q) => $q->where('id', $id))->sum('amount');
    }

    public function getAmountFormat($amt = 0)
    {
        return number_format($amt, 2);
    }

    public function sendMailIfRequired($action, $fields, $id = null)
    {

        $found = Template::where([
            "action_id" => $action,
            "medium" => "email"
        ])->first();


        if ($found) {
            $subject = $found->name;

            $body = str_replace(
                ['[title]', '[full_name]', '[from_date]', '[to_date]'],
                [
                    $fields['title'],
                    $fields['full_name'],
                    date('d-M-y', strtotime($fields['check_in'])),
                    date('d-M-y', strtotime($fields['check_out'])),
                ],
                $found->body
            );
            if ($fields['email']) {
                Mail::to($fields['email'])->send(new ActionMarkdownMail($body, $subject, $id));
                info("mail sent");
                return "mail sent";
            }

            info("email not provided");
            return "email not provided";
        }
    }

    public function sendWhatsappIfRequired($action, $fields, $company_id = 0)
    {

        $clientId = WhatsappClient::where("company_id", $company_id)->value("accounts")[0]["clientId"] ?? false;

        if (!$clientId) {
            Http::withoutVerifying()->post('https://wa.mytime2cloud.com/send-message', [
                'recipient' => "971554501483",
                'text' => "Whatsapp Account not found",
                'clientId' => $clientId,
            ]);
            return;
        }

        if (!$fields['whatsapp']) {
            Http::withoutVerifying()->post('https://wa.mytime2cloud.com/send-message', [
                'recipient' => "971554501483",
                'text' => "Whatsapp number not found",
                'clientId' => $clientId,
            ]);
            return;
        };

        $found = Template::where([
            "action_id" => $action,
            "medium" => "whatsapp"
        ])->first();

        if (!$found) {
            // Http::withoutVerifying()->post('https://wa.mytime2cloud.com/send-message', [
            //     'recipient' => "971554501483",
            //     'text' => "Template not found against $action action. Whatsapp client id : $clientId",
            //     'clientId' => $clientId,
            // ]);
            return;
        };

        $room_type = $fields['room_type'] ?? '';

        $subject = $found->name;

        $body = str_replace(
            ['[title]', '[full_name]', '[from_date]', '[to_date]', '[room_type]'],
            [
                $fields['title'],
                $fields['full_name'],
                date('d-M-y', strtotime($fields['check_in'])),
                date('d-M-y', strtotime($fields['check_out'])),
                $room_type
            ],
            $found->body
        );

        $body = preg_replace('/<p>(.*?)<\/p>/s', "$1\n\n", $body); // Convert <p> to new lines

        $body = strip_tags($body); // Ensure no remaining tags

        $response = Http::withoutVerifying()->post('https://wa.mytime2cloud.com/send-message', [
            'recipient' => "971554501483",
            'text' => trim($body), // Trim extra spaces
            'clientId' => $clientId,
        ]);

        return !$response->successful() ? false : true;
    }

    public function sendSignal($id)
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-Access-Key' => env("PUSHER_APP_KEY"),
                ])->post(env("SOCKET_SERVER_URL"), [
                    'id' => $id,
                ]);

            if ($response->successful()) {
                return $response->json();
                // Process the data as needed
            } else {
                return $response->body();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function normalizePhoneNumber($number)
    {
        // Remove whitespace and leading '+'
        $number = trim($number);
        if (strpos($number, '+') === 0) {
            $number = substr($number, 1);
        }

        // If number starts with '0', it's invalid (no country code)
        if (strpos($number, '0') === 0) {
            return false;
        }

        // Accept only digits
        if (!ctype_digit($number)) {
            return false;
        }

        return $number;
    }
}
