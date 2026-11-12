<?php

namespace Azimio\Messaging\Enums;

/**
 * Why a provider refused a submission, in our terms rather than theirs.
 *
 * Drivers map their own codes onto this so the pipeline (8.5) can decide
 * what to do without knowing which provider it is talking to. Every case
 * here is a PRE-SUBMISSION refusal: the message never reached the network,
 * so no credits are debited and any reservation is released (decision
 * 8.0.2).
 */
enum SendFailure: string
{
    /**
     * The sending account is out of money. On a tenant's own provider this
     * is what triggers the 8.0.6 branch: fail, then offer the AZIMIOCRM
     * retry only if the tenant has platform credits.
     */
    case InsufficientBalance = 'insufficient_balance';

    case InvalidNumber = 'invalid_number';
    case InvalidSender = 'invalid_sender';
    case InvalidContent = 'invalid_content';
    case InvalidSchedule = 'invalid_schedule';
    case TooManyRecipients = 'too_many_recipients';
    case AuthenticationFailed = 'authentication_failed';

    /** Transport failure, timeout, or anything the driver could not classify. */
    case ProviderError = 'provider_error';

    /**
     * Whether retrying the identical request could plausibly succeed later.
     * Used by the queue to decide between a retry and a terminal failure —
     * re-sending an invalid number or an unregistered sender never helps.
     */
    public function isTransient(): bool
    {
        return $this === self::ProviderError;
    }

    /**
     * Whether this is the tenant's own provider running dry, which is the
     * one failure the 8.0.6 retry offer exists for.
     */
    public function isBalanceProblem(): bool
    {
        return $this === self::InsufficientBalance;
    }
}
