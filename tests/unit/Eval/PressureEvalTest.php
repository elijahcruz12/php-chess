<?php

namespace Chess\Tests\Unit\Eval;

use Chess\Board;
use Chess\Player;
use Chess\Eval\PressureEval;
use Chess\Tests\AbstractUnitTestCase;

class PressureEvalTest extends AbstractUnitTestCase
{
    /**
     * @test
     */
    public function start()
    {
        $pressEval = (new PressureEval(new Board()))->eval();

        $expected = [
            'w' => [],
            'b' => [],
        ];

        $this->assertSame($expected, $pressEval);
    }

    /**
     * @test
     */
    public function open_sicilian()
    {
        $B56 = file_get_contents(self::DATA_FOLDER.'/sample/B56.pgn');

        $board = (new Player($B56))->play()->getBoard();

        $pressEval = (new PressureEval($board))->eval();

        $expected = [
            'w' => ['c6'],
            'b' => ['d4', 'e4'],
        ];

        $this->assertSame($expected, $pressEval);
    }

    /**
     * @test
     */
    public function closed_sicilian()
    {
        $B25 = file_get_contents(self::DATA_FOLDER.'/sample/B25.pgn');

        $board = (new Player($B25))->play()->getBoard();

        $pressEval = (new PressureEval($board))->eval();

        $expected = [
            'w' => [],
            'b' => ['c3'],
        ];

        $this->assertSame($expected, $pressEval);
    }

    /**
     * @test
     */
    public function e4_e5_Nf3_Nc6_Bb5_a6_Nxe5()
    {
        $board = new Board();
        $board->play('w', 'e4');
        $board->play('b', 'e5');
        $board->play('w', 'Nf3');
        $board->play('b', 'Nc6');
        $board->play('w', 'Bb5');
        $board->play('b', 'a6');
        $board->play('w', 'Nxe5');

        $pressEval = (new PressureEval($board))->eval();

        $expected = [
            'w' => ['a6', 'c6', 'c6', 'd7', 'f7'],
            'b' => ['b5', 'e5'],
        ];

        $this->assertSame($expected, $pressEval);
    }
}
