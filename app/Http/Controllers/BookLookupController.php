<?php

namespace App\Http\Controllers;

use App\Services\Books\BookLookup;
use App\Services\Books\WebCoverSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookLookupController extends Controller
{
    public function __invoke(string $isbn, BookLookup $lookup, Request $request, WebCoverSearch $webSearch): JsonResponse
    {
        if ($request->boolean('description')) {
            return response()->json($lookup->descriptionDetails($isbn, $request->boolean('open_library')))->header('Cache-Control', 'private, no-store');
        }
        if ($request->boolean('authors')) {
            return response()->json($lookup->authorDetails($isbn, $request->boolean('open_library')))->header('Cache-Control', 'private, no-store');
        }
        if ($request->boolean('web')) {
            return response()->json($webSearch->search($isbn, ! $request->boolean('automatic')))->header('Cache-Control', 'private, no-store');
        }

        return response()->json($request->boolean('alternatives') ? $lookup->alternativeCovers($isbn, $request->boolean('google')) : $lookup->lookup($isbn, $request->boolean('google'), $request->boolean('open_library')))->header('Cache-Control', 'private, no-store');
    }
}
