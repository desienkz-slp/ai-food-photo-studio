"""Strict, deterministic policy for structured edit plans; not a text parser."""
from dataclasses import dataclass


@dataclass(frozen=True)
class Decision:
    decision: str
    reasons: tuple


def evaluate(plan):
    """Validate an untrusted plan before returning allow, review, or deny.

    Only allow means the plan is policy-eligible, not authorized to execute.
    """
    def deny(reason):
        return Decision('deny', (reason,))

    if type(plan) is not dict or set(plan) != {'version', 'background', 'operations'}:
        return deny('invalid plan fields')
    if type(plan['version']) is not int or plan['version'] != 1:
        return deny('unsupported version')
    background = plan['background']
    if type(background) is not dict or type(background.get('mode')) is not str:
        return deny('invalid background')
    mode = background['mode']
    if mode in ('remove', 'replace'):
        return deny('validated protection evidence required; no execution boundary exists')
    if mode == 'keep':
        if set(background) != {'mode'}:
            return deny('invalid background fields')
    elif mode == 'replace':
        if (set(background) != {'mode', 'description'} or
                type(background['description']) is not str or
                not background['description'].strip() or len(background['description']) > 500):
            return deny('invalid replacement description')
    else:
        return deny('unsupported background mode')
    operations = plan['operations']
    if type(operations) is not list or len(operations) > 32:
        return deny('invalid operations')
    review = False
    for operation in operations:
        if type(operation) is not dict or set(operation) != {'target', 'action'}:
            return deny('invalid operation fields')
        target, action = operation['target'], operation['action']
        if type(target) is not str or type(action) is not str:
            return deny('invalid operation values')
        if target == 'product' and action == 'retouch':
            review = True
        elif target == 'background' and action == 'adjust_lighting':
            return deny('validated protection evidence required; no execution boundary exists')
        else:
            return deny('unsupported operation')
    if review:
        return Decision('review', ('product operation requires review; not executable',))
    return Decision('allow', ())


def evaluate_and_execute(plan, executor):
    """Fail closed until an authorized, trusted execution boundary exists."""
    evaluate(plan)
    return None


# Policy eligibility is not authorization; execution stays disabled until a
# trusted authorization and execution boundary are implemented.
