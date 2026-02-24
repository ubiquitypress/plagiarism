{**
 * plugins/generic/plagiarism/templates/message.tpl
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Show the submission file's iThenticate score after plagiarism check completed
 *}

<span class="plagiarism-similarity-message">
    <span>{$message|escape}</span>
</span>

<style>
    span.plagiarism-similarity-message {
        display: flex;
        align-items: center;
    }

    span.plagiarism-similarity-message img {
        max-width: 100px;
    }

    span.plagiarism-similarity-message span {
        color: #000000d6;
    }
</style>
