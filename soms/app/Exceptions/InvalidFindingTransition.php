<?php
// app/Exceptions/InvalidFindingTransition.php

namespace App\Exceptions;

use RuntimeException;

/** A finding was asked to move to a status it can't move to from where it is. */
class InvalidFindingTransition extends RuntimeException
{
}
