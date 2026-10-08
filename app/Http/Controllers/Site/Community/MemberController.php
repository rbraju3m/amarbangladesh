<?php

namespace App\Http\Controllers\Site\Community;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\Bangla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    /** A new device identity. The token is returned once; only its hash is stored. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:40']]);
        [$member, $token] = Member::register(Bangla::cleanName($data['name'] ?? null));

        return response()->json(['member' => self::present($member), 'token' => $token], 201);
    }

    /** Who this token belongs to: used to restore an identity from the private link on another device. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['member' => self::present($request->attributes->get('member'))]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40']]);
        $member = $request->attributes->get('member');
        $name = Bangla::cleanName($data['name']);
        abort_unless($name, 422, 'নামটা ঠিকমতো লেখো।');
        $member->update(['name' => $name]);

        return response()->json(['member' => self::present($member)]);
    }

    public static function present(Member $member): array
    {
        return ['code' => $member->code, 'name' => $member->name, 'url' => route('members.show', $member, false)];
    }
}
