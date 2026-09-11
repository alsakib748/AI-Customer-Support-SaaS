<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WidgetController extends Controller
{

    /**
     * Embed widget in an iframe-friendly page
     */
    public function embed(Request $request)
    {
        $widgetId = $request->query('widget_id');

        return view('widget.embed', [
            'widgetId' => $widgetId,
            'apiUrl' => config('app.url') . '/api/v1/widget',
        ]);
    }
}