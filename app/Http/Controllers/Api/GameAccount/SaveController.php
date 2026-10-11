<?php

namespace App\Http\Controllers\Api\GameAccount;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveController extends Controller
{
    private function validatePayload(Request $request): array
    {
        abort_if(strlen($request->getContent()) > 65536, 413, '存档过大');
        $rules = [
            'payload' => 'required|array:v,gold,mastery,equip,bag,best,runs,chapterCompleted,storySeen,weaponCount,loadout',
            'payload.v' => 'required|integer|in:1,2',
            'payload.gold' => 'required|integer|between:0,100000000',
            'payload.mastery' => 'present|array:sword,dagger,greatsword,spear,staff,boomerang,knife',
            'payload.mastery.*' => 'integer|between:0,10000000',
            'payload.weaponCount' => 'sometimes|array:sword,boomerang,knife',
            'payload.weaponCount.*' => 'integer|between:0,10000000',
            'payload.loadout' => 'sometimes|array:weapon,mods',
            'payload.loadout.weapon' => 'required_with:payload.loadout|in:sword,boomerang,knife',
            'payload.loadout.mods' => 'present_with:payload.loadout|array:sword',
            'payload.loadout.mods.sword' => 'sometimes|in:giant,swift',
            'payload.equip' => 'required|array:weapon,armor,trinket',
            'payload.bag' => 'present|array|max:5',
            'payload.best' => 'required|array:wave,kills',
            'payload.best.wave' => 'required|integer|between:1,10000',
            'payload.best.kills' => 'required|integer|between:0,100000000',
            'payload.runs' => 'required|integer|between:0,10000000',
            'payload.chapterCompleted' => 'required|integer|between:0,1',
            'payload.storySeen' => 'required|boolean',
        ];
        foreach (['payload.equip.weapon', 'payload.equip.armor', 'payload.equip.trinket', 'payload.bag.*'] as $prefix) {
            $rules[$prefix] = 'nullable|array:id,slot,slotName,kind,name,rarity,rarityName,color,affixes,score';
            foreach (['id' => 64, 'slotName' => 24, 'name' => 48, 'rarityName' => 24] as $field => $max) {
                $rules[$prefix.'.'.$field] = 'required_with:'.$prefix.'|string|max:'.$max;
            }
            $rules[$prefix.'.slot'] = 'required_with:'.$prefix.'|in:weapon,armor,trinket';
            $rules[$prefix.'.kind'] = 'nullable|in:sword,dagger,greatsword,spear,staff,boomerang,knife';
            $rules[$prefix.'.rarity'] = 'required_with:'.$prefix.'|integer|between:1,4';
            $rules[$prefix.'.color'] = ['required_with:'.$prefix, 'regex:/^#[a-fA-F0-9]{6}$/'];
            $rules[$prefix.'.score'] = 'required_with:'.$prefix.'|numeric|between:0,100000';
            $rules[$prefix.'.affixes'] = 'sometimes|array|max:8';
            $rules[$prefix.'.affixes.*'] = 'array:k,v,label,negate';
            $rules[$prefix.'.affixes.*.k'] = 'required|string|in:attackDamage,maxhp,attackCooldown,spd,crit,lifesteal,dashCooldown,attackRange,armor,pickupRange';
            $rules[$prefix.'.affixes.*.v'] = 'required|numeric|between:0,1000';
            $rules[$prefix.'.affixes.*.label'] = 'required|string|max:32';
            $rules[$prefix.'.affixes.*.negate'] = 'sometimes|boolean';
        }
        $payload = Validator::make($request->all(), $rules)->validate()['payload'];
        foreach (['weapon', 'armor', 'trinket'] as $slot) {
            $item = $payload['equip'][$slot] ?? null;
            if ($item !== null && (! isset($item['affixes']) || ($item['slot'] ?? null) !== $slot || $slot === 'weapon' && empty($item['kind']))) {
                throw ValidationException::withMessages(['payload.equip.'.$slot => '装备格式或槽位不正确']);
            }
        }
        foreach ($payload['bag'] as $item) {
            if (! $item || ! isset($item['affixes']) || ($item['slot'] ?? null) !== 'weapon' || empty($item['kind'])) {
                throw ValidationException::withMessages(['payload.bag' => '武器库格式不正确']);
            }
        }

        if (isset($payload['loadout'])) {
            if (! isset($payload['loadout']['weapon'], $payload['loadout']['mods'])) {
                throw ValidationException::withMessages(['payload.loadout' => '出战配置需要武器和改造字段']);
            }
            $owned = array_column($payload['bag'], 'kind');
            $owned[] = $payload['equip']['weapon']['kind'] ?? 'sword';
            $owned[] = 'sword';
            if (! in_array($payload['loadout']['weapon'], $owned, true)) {
                throw ValidationException::withMessages(['payload.loadout.weapon' => '只能选择已拥有的武器']);
            }
            $mod = $payload['loadout']['mods']['sword'] ?? null;
            $need = ['giant' => 300, 'swift' => 600];
            if ($mod && ($payload['mastery']['sword'] ?? 0) < $need[$mod]) {
                throw ValidationException::withMessages(['payload.loadout.mods.sword' => '熟练度尚未解锁该改造']);
            }
        }

        return $payload;
    }

    public function onboarding(Request $request)
    {
        $request->validate(['source' => 'required|in:tutorial,legacy']);
        $payload = $this->validatePayload($request);
        if ($request->input('source') === 'tutorial') {
            // One fixed reward. Tutorial combat never imports coins, mastery or randomized loot.
            $payload = ['v' => $payload['v'], 'weaponCount' => [], 'loadout' => ['weapon' => 'sword', 'mods' => []], 'gold' => 0, 'mastery' => [], 'equip' => ['weapon' => null, 'armor' => [
                'id' => 'prologue-armor', 'slot' => 'armor', 'slotName' => '护甲', 'kind' => null, 'name' => '守夜者旧甲',
                'rarity' => 1, 'rarityName' => '普通', 'color' => '#c9ccd2', 'affixes' => [['k' => 'maxhp', 'v' => 10, 'label' => '生命']], 'score' => 10],
                'trinket' => null], 'bag' => [], 'best' => ['wave' => 1, 'kills' => 0], 'runs' => 0, 'chapterCompleted' => 0, 'storySeen' => false];
        }

        return DB::transaction(function () use ($request, $payload) {
            $id = $request->attributes->get('game_player')->id;
            $player = DB::table('game_players')->where('id', $id)->lockForUpdate()->first();
            if (! $player->tutorial_completed) {
                DB::table('game_player_saves')->insert(['player_id' => $id, 'revision' => 1, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
                DB::table('game_players')->where('id', $id)->update(['tutorial_completed' => true, 'updated_at' => now()]);
            }
            $s = DB::table('game_player_saves')->where('player_id', $id)->first();

            return response()->json(['data' => ['revision' => $s->revision, 'payload' => json_decode($s->payload, true)]]);
        });
    }

    public function update(Request $request)
    {
        $request->validate(['revision' => 'required|integer|min:1', 'request_id' => 'required|uuid']);
        $payload = $this->validatePayload($request);
        $hash = hash('sha256', json_encode($payload));

        return DB::transaction(function () use ($request, $payload, $hash) {
            $p = $request->attributes->get('game_player');
            abort_unless($p->tutorial_completed, 403, '请先完成序章');
            $s = DB::table('game_player_saves')->where('player_id', $p->id)->lockForUpdate()->first();
            abort_unless($s, 409, '云存档尚未初始化');
            if ($s->request_id === $request->input('request_id')) {
                abort_unless(hash_equals($s->request_hash ?? '', $hash), 409, '保存请求编号重复');

                return response()->json(['data' => ['revision' => $s->revision]]);
            }
            if ($s->revision !== (int) $request->input('revision')) {
                return response()->json(['message' => '云存档已更新，请选择要保留的进度', 'data' => ['revision' => $s->revision, 'payload' => json_decode($s->payload, true)]], 409);
            }
            DB::table('game_save_backups')->insert(['player_id' => $p->id, 'revision' => $s->revision, 'payload' => $s->payload, 'created_at' => now()]);
            $keep = DB::table('game_save_backups')->where('player_id', $p->id)->orderByDesc('id')->limit(20)->pluck('id')->all();
            DB::table('game_save_backups')->where('player_id', $p->id)->whereNotIn('id', $keep)->delete();
            DB::table('game_player_saves')->where('player_id', $p->id)->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'revision' => $s->revision + 1, 'request_id' => $request->input('request_id'), 'request_hash' => $hash, 'updated_at' => now()]);

            return response()->json(['data' => ['revision' => $s->revision + 1]]);
        });
    }
}
