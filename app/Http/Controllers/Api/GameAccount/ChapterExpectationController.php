<?php

namespace App\Http\Controllers\Api\GameAccount;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChapterExpectationController extends Controller
{
    public function show(Request $request)
    {
        $expected = DB::table('game_chapter_expectations')
            ->where('player_id', $request->attributes->get('game_player')->id)
            ->where('chapter', 2)->exists();

        return response()->json(['data' => ['chapter' => 2, 'expected' => $expected]]);
    }

    public function store(Request $request)
    {
        // The account is always derived from the authenticated session.
        // A unique key also deduplicates simultaneous requests and retries.
        DB::table('game_chapter_expectations')->upsert([
            'player_id' => $request->attributes->get('game_player')->id,
            'chapter' => 2,
            'created_at' => now(),
        ], ['player_id', 'chapter'], ['chapter']);

        return response()->json(['data' => ['chapter' => 2, 'expected' => true]]);
    }
}
