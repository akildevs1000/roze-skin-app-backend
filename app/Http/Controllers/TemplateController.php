<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
class TemplateController extends Controller
{
    public function templateTypes()
    {
        // $customer = BookedRoom::where("status_id", BookedRoom::CHECKED_IN)->with("customer")->get();

        // $checkIn  = false;
        // $checkOut  = false;

        // if ($customer->checkin !== null && $customer->checkout == null) {
        //     $checkIn = true;
        // } else if ($customer->checkin !== null && $customer->checkout !== null) {
        //     $checkOut = true;
        // }

        return [
            ["id" => 1, "name" => 'Order Received'],
            ["id" => 2, "name" => 'Order Dispatched'],
            ["id" => 3, "name" => 'Birthday Wish'],
        ];
    }
    /**
     * Display a listing of the resource for dropdown.
     *
     * @return \Illuminate\Http\Response
     */
    public function dropDown()
    {
        return Template::orderByDesc("name")
            ->where([
                "medium" => request("medium", "email")
            ])
            ->get();
    }

    /**
     * Display a listing of the resource with pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Template::orderByDesc("id")->where([
            "medium" => request("medium", "email")
        ])->paginate(request("per_page", 15));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Template $template)
    {
        $data = $request->validate($template::validateFields);

        return Template::create($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Template  $template
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Template $template)
    {
        $data = $request->validate($template::validateFields);

        $template->update($data);

        return $template;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Template  $template
     * @return \Illuminate\Http\Response
     */
    public function destroy(Template $template)
    {
        $template->delete();

        return response()->noContent();
    }
}
