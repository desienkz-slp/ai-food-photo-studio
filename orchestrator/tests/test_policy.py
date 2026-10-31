import unittest

from orchestrator.policy import evaluate, evaluate_and_execute


class PolicyTests(unittest.TestCase):
    def test_background_replacement_without_validated_protection_is_denied(self):
        result = evaluate({'version': 1, 'background': {'mode': 'replace', 'description': 'white marble'}, 'operations': []})
        self.assertEqual(result.decision, 'deny')
        self.assertIn('validated protection evidence', result.reasons[0])

    def test_background_removal_without_validated_protection_is_denied(self):
        result = evaluate({'version': 1, 'background': {'mode': 'remove'}, 'operations': []})
        self.assertEqual(result.decision, 'deny')
        self.assertIn('validated protection evidence', result.reasons[0])

    def test_invalid_or_ambiguous_plans_never_allow(self):
        cases = [None, {}, {'version': True, 'background': {'mode': 'keep'}, 'operations': []},
                 {'version': 1, 'background': {'mode': 'unknown'}, 'operations': []},
                 {'version': 1, 'background': {'mode': 'replace', 'description': ''}, 'operations': []},
                 {'version': 1, 'background': {'mode': 'keep'}, 'operations': [], 'instruction': 'make it better'},
                 {'version': 1, 'background': {'mode': 'keep'}, 'operations': [{'target': 'product', 'action': 'generate'}]},
                 {'version': 1, 'background': {'mode': 'keep'}, 'operations': [{'target': 'background', 'action': 'unknown'}]}]
        for plan in cases:
            with self.subTest(plan=plan):
                self.assertNotEqual(evaluate(plan).decision, 'allow')

    def test_malformed_nested_fields_fail_closed(self):
        base = {'version': 1, 'background': {'mode': 'keep'}, 'operations': []}
        cases = [
            dict(base, background={'mode': 'keep', 'description': 'unexpected'}),
            dict(base, background={'mode': 'remove', 'extra': True}),
            dict(base, background={'mode': []}),
            dict(base, background={'mode': 'replace', 'description': '   '}),
            dict(base, operations=None),
            dict(base, operations=[{'target': 'background', 'action': 'adjust_lighting', 'extra': 1}]),
            dict(base, operations=[{'target': [], 'action': 'adjust_lighting'}]),
            dict(base, operations=[{'target': 'product', 'action': 'retouch'}, {'target': 'product', 'action': 'generate'}]),
        ]
        for plan in cases:
            with self.subTest(plan=plan):
                self.assertEqual(evaluate(plan).decision, 'deny')
                self.assertTrue(evaluate(plan).reasons)

    def test_background_mutations_are_not_eligible_without_protection_evidence(self):
        for background in ({'mode': 'remove'}, {'mode': 'replace', 'description': 'white marble'}):
            with self.subTest(background=background):
                self.assertEqual(evaluate({'version': 1, 'background': background, 'operations': []}).decision, 'deny')

    def test_keep_background_remains_allowable(self):
        self.assertEqual(evaluate({'version': 1, 'background': {'mode': 'keep'}, 'operations': []}).decision, 'allow')

    def test_product_operations_require_review(self):
        plan = {'version': 1, 'background': {'mode': 'keep'}, 'operations': [{'target': 'product', 'action': 'retouch'}]}
        self.assertEqual(evaluate(plan).decision, 'review')

    def test_lighting_without_validated_protection_is_denied(self):
        plan = {'version': 1, 'background': {'mode': 'keep'}, 'operations': [{'target': 'background', 'action': 'adjust_lighting'}]}
        self.assertEqual(evaluate(plan).decision, 'deny')
        self.assertIn('validated protection evidence', evaluate(plan).reasons[0])

    def test_allow_never_calls_arbitrary_executor_until_authorized_boundary_exists(self):
        calls = []
        executor = lambda plan: calls.append(plan)
        plan = {'version': 1, 'background': {'mode': 'keep'}, 'operations': []}

        self.assertIsNone(evaluate_and_execute(plan, executor))
        self.assertEqual(calls, [])

    def test_deny_and_review_never_call_executor(self):
        calls = []
        executor = lambda plan: calls.append(plan)
        for plan in (
            {'version': 1, 'background': {'mode': 'remove'}, 'operations': []},
            {'version': 1, 'background': {'mode': 'keep'}, 'operations': [{'target': 'product', 'action': 'retouch'}]},
        ):
            with self.subTest(plan=plan):
                self.assertIsNone(evaluate_and_execute(plan, executor))
        self.assertEqual(calls, [])


if __name__ == '__main__':
    unittest.main()
