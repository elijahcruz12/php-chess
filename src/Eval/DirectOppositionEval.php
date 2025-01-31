<?php

namespace Chess\Eval;

use Chess\Variant\Classical\PGN\AN\Color;
use Chess\Variant\Classical\PGN\AN\Piece;
use Chess\Variant\Classical\Board;

class DirectOppositionEval extends AbstractEval
{
    const NAME = 'Direct opposition';

    public function __construct(Board $board)
    {
        parent::__construct($board);

        $this->result = [
            Color::W => 0,
            Color::B => 0,
        ];
    }

    public function eval(): array
    {
        $wK = $this->board->getPiece(Color::W, Piece::K)->getSq();
        $bK = $this->board->getPiece(Color::B, Piece::K)->getSq();

        if ($wK[0] === $bK[0]) {
            if (abs($wK[1] - $bK[1]) === 2) {
                $this->result = [
                    Color::W => (int) ($this->board->getTurn() !== Color::W),
                    Color::B => (int) ($this->board->getTurn() !== Color::B),
                ];
            }
        }

        if ($wK[1] === $bK[1]) {
            if (abs(ord($wK[0]) - ord($bK[0])) === 2) {
                $this->result = [
                    Color::W => (int) ($this->board->getTurn() !== Color::W),
                    Color::B => (int) ($this->board->getTurn() !== Color::B),
                ];
            }
        }

        return $this->result;
    }
}
