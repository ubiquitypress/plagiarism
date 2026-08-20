/**
 * Whether a similarity score is available for a file status entry.
 *
 * The API flattens the stored report down to `overall_match_percentage`, so the score is a plain
 * number and `0` is a perfectly valid result — a document that matched nothing. Every consumer must
 * therefore test for *presence*, never for truthiness: `0` is falsy in JavaScript, and a truthiness
 * test renders a completed 0% check as though it were still processing (pkp/plagiarism#128).
 *
 * Loose `!= null` is deliberate — it covers both `null` (no score yet) and `undefined`
 * (`deduceFileStatus()` can return an empty object, or no entry at all, for an unknown file).
 */
export function hasSimilarityScore(fileStatus)
{
    return fileStatus?.ithenticateSimilarityResult != null;
}

export function deduceFileStatus(submissionFile, ithenticateDataStatus)
{
    const fileId =  submissionFile.id;
    const sourceSubmissionFileId = submissionFile.sourceSubmissionFileId;

    if (ithenticateDataStatus?.files?.[fileId]?.ithenticateId) {
        return ithenticateDataStatus?.files?.[fileId];
    }

    const sourceSubmissionFile = ithenticateDataStatus?.files?.[sourceSubmissionFileId];

    if (sourceSubmissionFile && sourceSubmissionFile.fileId === submissionFile.fileId) {
        return sourceSubmissionFile;
    }

    return ithenticateDataStatus?.files?.[fileId];
}