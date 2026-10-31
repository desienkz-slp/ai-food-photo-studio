import unittest

from review_domain import ExportDenied, Review, ReviewStatus, VersionId


class ReviewDomainTests(unittest.TestCase):
    def test_version_id_is_immutable_and_validates_text(self):
        version = VersionId("v-001")
        self.assertEqual(str(version), "v-001")
        with self.assertRaises((AttributeError, TypeError)):
            version.value = "v-002"
        with self.assertRaises(ValueError):
            VersionId("")

    def test_new_review_is_in_review_and_approval_returns_new_approved_snapshot(self):
        review = Review("v-001")
        self.assertEqual(review.status, ReviewStatus.REVIEW)
        approved = review.approve()
        self.assertEqual(approved.status, ReviewStatus.APPROVED)
        self.assertEqual(review.status, ReviewStatus.REVIEW)
        self.assertEqual(approved.version_id, VersionId("v-001"))

    def test_export_requires_approval_for_exact_version(self):
        review = Review("v-001")
        with self.assertRaises(ExportDenied):
            review.export()
        self.assertEqual(review.approve().export(), "v-001")

    def test_approval_for_another_version_does_not_authorize_export(self):
        approved = Review("v-001").approve()
        changed_version = Review("v-002")
        with self.assertRaises(ExportDenied):
            changed_version.export(approved)

    def test_invalid_version_and_invalid_approval_fail_closed(self):
        with self.assertRaises(ValueError):
            Review("  ")
        with self.assertRaises(ExportDenied):
            Review("v-001").export(object())


if __name__ == "__main__":
    unittest.main()
