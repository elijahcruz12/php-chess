<?php

namespace Chess;

use Chess\Ascii;
use Chess\StringToBoard;
use Chess\HeuristicPicture;
use Chess\FEN\BoardToString;
use Chess\FEN\ShortenedStringToPgn;
use Chess\PGN\Convert;
use Chess\PGN\Symbol;
use Chess\PGN\Validate;
use Chess\Evaluation\PressureEvaluation;
use Chess\Evaluation\SpaceEvaluation;
use Chess\ML\Supervised\Classification\LinearCombinationPredictor;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;

/**
 * Game class.
 *
 * A wrapper of the Board class.
 *
 * @author Jordi Bassagañas
 * @license GPL
 */
class Game
{
    const MODE_AI           =  'MODE_AI';

    const MODE_ANALYSIS     =  'MODE_ANALYSIS';

    const MODEL_FOLDER      = __DIR__.'/../model/';

    /**
     * Chess board.
     *
     * @var Board
     */
    private $board;

    /**
     * Mode.
     *
     * @var string
     */
    private $mode;

    /**
     * Estimator.
     *
     * @var PersistentModel
     */
    private $estimator;

    /**
     * Constructor.
     */
    public function __construct(string $mode = null, string $model = 'a1.model')
    {
        $this->board = new Board();
        $this->mode = $mode ?? self::MODE_ANALYSIS;
        $this->estimator = PersistentModel::load(new Filesystem(self::MODEL_FOLDER.$model));
    }

    /**
     * Gets the board's status.
     *
     * @return \stdClass
     */
    public function status(): \stdClass
    {
        return (object) [
            'turn' => $this->board->getTurn(),
            'castling' => $this->board->getCastling(),
            'squares' => $this->board->getSquares(),
            PressureEvaluation::NAME => $this->board->getPressure(),
            SpaceEvaluation::NAME => $this->board->getSpace(),
        ];
    }

    public function castling(): ?array
    {
        return $this->board->getCastling();
    }

    /**
     * Gets the history.
     *
     * @return array
     */
    public function history(): array
    {
        $history = [];

        $boardHistory = $this->board->getHistory();

        foreach ($boardHistory as $entry) {
            $history[] = (object) [
                'pgn' => $entry->move->pgn,
                'color' => $entry->move->color,
                'identity' => $entry->move->identity,
                'position' => $entry->position,
                'isCapture' => $entry->move->isCapture,
                'isCheck' => $entry->move->isCheck,
            ];
        }

        return $history;
    }

    /**
     * Gets the movetext.
     *
     * @return string
     */
    public function movetext(): string
    {
        return $this->board->getMovetext();
    }

    /**
     * Gets the pieces captured by both players.
     *
     * @return \stdClass
     */
    public function captures(): array
    {
        return $this->board->getCaptures();
    }

    /**
     * Gets an array of pieces by color.
     *
     * @param string $color
     * @return array
     */
    public function pieces(string $color): array
    {
        $result = [];

        $pieces = $this->board->getPiecesByColor(Validate::color($color));

        foreach ($pieces as $piece) {
            $result[] = (object) [
                'identity' => $piece->getIdentity(),
                'position' => $piece->getPosition(),
                'moves' => $piece->getLegalMoves(),
            ];
        }

        return $result;
    }

    /**
     * Gets a piece by its position on the board.
     *
     * @param string $square
     * @return mixed null|\stdClass
     */
    public function piece(string $square): ?\stdClass
    {
        $piece = $this->board->getPieceByPosition(Validate::square($square));

        if ($piece) {
            $result = [
                'color' => $piece->getColor(),
                'identity' => $piece->getIdentity(),
                'position' => $piece->getPosition(),
                'moves' => $piece->getLegalMoves(),
            ];
            if ($piece->getIdentity() === Symbol::PAWN) {
                if ($enPassant = $piece->getEnPassantSquare()) {
                    $result['enPassant'] = $enPassant;
                }
            }

            return (object) $result;
        }

        return null;
    }

    /**
     * Calculates whether the current player is checked.
     *
     * @return bool
     */
    public function isCheck(): bool
    {
        return $this->board->isCheck();
    }

    /**
     * Calculates whether the current player is mated.
     *
     * @return bool
     */
    public function isMate(): bool
    {
        return $this->board->isMate();
    }

    /**
     * Plays a chess move on the board.
     *
     * @param string $color
     * @param string $pgn
     * @return bool
     */
    public function play(string $color, string $pgn): bool
    {
        return $this->board->play(Convert::toStdObj($color, $pgn));
    }

    /**
     * Gets the events taking place on the board.
     *
     * @return \stdClass
     */
    public function events(): \stdClass
    {
        return $this->board->events();
    }

    /**
     * AI model response to the current position.
     *
     * @return string
     */
    public function response()
    {
        $response = (new Grandmaster())
            ->response($this->board->getMovetext());

        if ($response) {
            return $response;
        }

        $response = (new LinearCombinationPredictor($this->board, $this->estimator))
            ->predict();

        return $response;
    }

    public function ascii(): string
    {
        return (new Ascii($this->board))->print();
    }

    public function fen(): string
    {
        return (new BoardToString($this->board))->create();
    }

    public function loadFen(string $string)
    {
        $this->board = (new StringToBoard($string))->create();
    }

    public function playFen(string $toShortenedFen)
    {
        $fromFen = (new BoardToString($this->board))->create();

        $fromPiecePlacement = explode(' ', $fromFen)[0];
        $toPiecePlacement = explode(' ', $toShortenedFen)[0];
        $fromRanks = explode('/', $fromPiecePlacement);
        $toRanks = explode('/', $toPiecePlacement);

        if (
          'K2R' === substr($fromRanks[7], -3) &&
          '2KR' === substr($toRanks[7], -3) &&
          $this->board->play(Convert::toStdObj(Symbol::WHITE, Symbol::CASTLING_SHORT))
        ) {
            return Symbol::CASTLING_SHORT;
        } elseif (
          'R3K' === substr($fromRanks[7], 0, 3) &&
          'R1K' === substr($toRanks[7], 0, 3) &&
          $this->board->play(Convert::toStdObj(Symbol::WHITE, Symbol::CASTLING_LONG))
        ) {
            return Symbol::CASTLING_LONG;
        } elseif (
          'k2r' === substr($fromRanks[0], -3) &&
          '2kr' === substr($toRanks[0], -3) &&
          $this->board->play(Convert::toStdObj(Symbol::BLACK, Symbol::CASTLING_SHORT))
        ) {
            return Symbol::CASTLING_SHORT;
        } elseif (
          'r3k' === substr($fromRanks[0], 0, 3) &&
          'r1k' === substr($toRanks[0], 0, 3) &&
          $this->board->play(Convert::toStdObj(Symbol::BLACK, Symbol::CASTLING_LONG))
        ) {
            return Symbol::CASTLING_LONG;
        }

        $pgn = (new ShortenedStringToPgn($fromFen, $toShortenedFen))->create();
        $color = key($pgn);
        $result = current($pgn);

        if ($result) {
            return $this->board->play(Convert::toStdObj($color, $result));
        }

        return false;
    }
}
