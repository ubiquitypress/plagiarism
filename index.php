<?php

/**
 * @file index.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief Wrapper for plagiarism checking plugin.
 */
require_once('PlagiarismPlugin.inc.php');
return new PlagiarismPlugin();
