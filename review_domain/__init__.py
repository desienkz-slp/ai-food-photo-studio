from dataclasses import dataclass
from enum import Enum


class ExportDenied(Exception):
    """Raised when the exact version has not been approved."""


class ReviewStatus(Enum):
    REVIEW = "review"
    APPROVED = "approved"


@dataclass(frozen=True)
class VersionId:
    value: str

    def __post_init__(self):
        if type(self.value) is not str or not self.value.strip():
            raise ValueError("version identifier must be non-empty text")

    def __str__(self):
        return self.value


@dataclass(frozen=True)
class Review:
    version_id: VersionId
    status: ReviewStatus = ReviewStatus.REVIEW

    def __init__(self, version_id):
        object.__setattr__(self, "version_id", version_id if isinstance(version_id, VersionId) else VersionId(version_id))
        object.__setattr__(self, "status", ReviewStatus.REVIEW)

    def approve(self):
        return Review._approved(self.version_id)

    @classmethod
    def _approved(cls, version_id):
        instance = object.__new__(cls)
        object.__setattr__(instance, "version_id", version_id)
        object.__setattr__(instance, "status", ReviewStatus.APPROVED)
        return instance

    def export(self, approval=None):
        if approval is None:
            approval = self
        if not isinstance(approval, Review) or approval.status is not ReviewStatus.APPROVED or approval.version_id != self.version_id:
            raise ExportDenied("exact version approval required")
        return str(self.version_id)
