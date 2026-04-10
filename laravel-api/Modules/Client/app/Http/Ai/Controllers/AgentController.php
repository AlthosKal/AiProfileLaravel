<?php

namespace Modules\Client\Http\Ai\Controllers;

use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Modules\Client\Actions\PromptAgentAction;
use Modules\Client\Http\Ai\Data\PromptRequestData;

class AgentController
{
    public function prompt(Request $request, PromptAgentAction $action): mixed
    {
        $data = PromptRequestData::from($request->all());

        /** @var User $user */
        $user = $request->user();

        return $action->execute($data, $user)
            ->usingVercelDataProtocol();
    }
}
