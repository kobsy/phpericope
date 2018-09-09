<?php

require_once __DIR__ . "/../src/pericope/data.php";

use PHPUnit\Framework\TestCase;
use PHPericope\Pericope;
use PHPericope\Verse;
use PHPericope\Range;

final class PericopeTest extends TestCase {

  public function testBookPatternMatchesValidBooks() {
    $tests = array(
      'ii samuel' => 'ii samuel',
      '1 cor.' => '1 cor',
      'jas' => 'jas',
      'song of songs' => 'song of songs',
      'song of solomon' => 'song of solomon',
      'first kings' => 'first kings',
      '3rd jn' => '3rd jn',
      'phil' => 'phil');

    foreach($tests as $input=>$expected_match) {
      preg_match('/' . PHPericope\BOOK_PATTERN . '/ix', $input, $matches);
      $this->assertEquals($expected_match, $matches[0], "Expected Pericope to recognize $input as a potential book");
    }
  }

  public function testRegexpMatchesThingsThatLookLikePericopes() {
    $tests = array('Romans 3:9');

    foreach($tests as $input) {
      $result = preg_match(Pericope::regexp(), $input);
      $this->assertEquals(1, $result, "Expected Pericope to recognize $input as a potential pericope");
    }
  }

  public function testRegexpDoesNotMatchThingsThatDoNotLookLikePericopes() {
    $tests = array('Cross 1', 'Hezekiah 4:3');

    foreach($tests as $input) {
      $result = preg_match(Pericope::regexp(), $input);
      $this->assertEquals(0, $result, "Expected Pericope to recognize that $input is not a potential pericope");
    }
  }

  public function testGetMaxChapterReturnsLastChapterOfBook() {
    $tests = array(1 => 50, 19 => 150, 65 => 1, 66 => 22);

    foreach($tests as $book=>$chapters) {
      $this->assertEquals($chapters, Pericope::get_max_chapter($book));
    }
  }

  public function testGetMaxVerseReturnsLastVerseOfChapter() {
    $tests = array(
      array('book' => 1, 'chapter' => 9, 'verses' => 29),
      array('book' => 1, 'chapter' => 50, 'verses' => 26)
    );

    foreach($tests as $test) {
      $this->assertEquals($test['verses'], Pericope::get_max_verse($test['book'], $test['chapter']));
    }
  }

  public function testBookHasChapters() {
    $this->assertTrue(Pericope::has_chapters(1),   "Genesis has chapters");
    $this->assertTrue(Pericope::has_chapters(23),  "Isaiah has chapters");
    $this->assertFalse(Pericope::has_chapters(57), "Philemon does not have chapters");
    $this->assertFalse(Pericope::has_chapters(65), "Jude does not have chapters");
  }

  public function testIdentifyingBooksOfTheBible() {
    $tests = array('Romans' => 45, 'mark' => 41, 'ps' => 19, 'jas' => 59, 'ex' => 2);

    foreach($tests as $input=>$expected_number) {
      $this->assertEquals($expected_number, \PHPericope\parse_one("$input 1")->book, "Expected Pericope to be able to identify \"$input\" as book #$expected_number");
    }
  }

  public function testParsingReferenceFragmentSplitsChapterAndVerse() {
    $this->assertEquals(new PHPericope\ReferenceFragment(3, 45), PHPericope\parse_reference_fragment("3:45"));
  }

  public function testParsingReferenceFragmentIgnoresDefaultChapterWhenInputContainsChapterAndVerse() {
    $this->assertEquals(new PHPericope\ReferenceFragment(3, 45), PHPericope\parse_reference_fragment("3:45", 11));
  }

  public function testParsingReferenceFragmentUsesDefaultChapterWhenInputContainsOneNumber() {
    $this->assertEquals(new PHPericope\ReferenceFragment(11, 45), PHPericope\parse_reference_fragment("45", 11));
  }

  public function testParsingReferenceFragmentLeavesVerseBlankWhenOneNumberAndDefaultChapterNull() {
    $this->assertEquals(new PHPericope\ReferenceFragment(45), PHPericope\parse_reference_fragment("45"));
  }

  public function testParsingReferenceParsesRangeOfVerses() {
    $expected = array(new Range(Verse::parse(19001001), Verse::parse(19008009)));
    $this->assertEquals($expected, PHPericope\parse_reference(19, "1-8")); // Psalm 1-8
  }

  public function testParsingReferenceParsesRangeOfVersesSpanningChapter() {
    $expected = array(new Range(Verse::parse(43012001), Verse::parse(43013008)));
    $this->assertEquals($expected, PHPericope\parse_reference(43, "12:1-13:8")); // John 12:1-13:8
  }

  public function testParsingReferenceParsesSingleVerseAsRangeOfOne() {
    $expected = array(new Range(Verse::parse(60001001), Verse::parse(60001001)));
    $this->assertEquals($expected, PHPericope\parse_reference(60, "1:1")); // 1 Peter 1:1
  }

  public function testParsingReferenceParsesChapterIntoRangeOfVersesInThatChapter() {
    $expected = array(new Range(Verse::parse(1001001), Verse::parse(1001031)));
    $this->assertEquals($expected, PHPericope\parse_reference(1, "1")); // Genesis 1
  }

  public function testParsingReferenceParsesMultipleRangesIntoAnArrayOfRanges() {
    $expected_ranges = array(
      new Range(Verse::parse(40003001), Verse::parse(40003001)),
      new Range(Verse::parse(40003003), Verse::parse(40003003)),
      new Range(Verse::parse(40003004), Verse::parse(40003005)),
      new Range(Verse::parse(40003007), Verse::parse(40003007)),
      new Range(Verse::parse(40004019), Verse::parse(40004019))
    );
    $tests = array("3:1,3,4-5,7; 4:19", "3:1, 3 ,4-5; 7,4:19");

    foreach($tests as $input) {
      $this->assertEquals($expected_ranges, PHPericope\parse_reference(40, $input)); // Matthew 3:1,3,4-5,7; 4:19
    }
  }

  public function testParsingReferenceAllowsVariousPunctuationErrorsForChapterVersePairing() {
    $tests = array('1:4-9', '1"4-9', '1.4-9', '1 :4-9', '1: 4-9');

    foreach($tests as $input) {
      $this->assertEquals(array(new Range(Verse::parse(28001004), Verse::parse(28001009))), PHPericope\parse_reference(28, $input));
    }
  }

  public function testParsingReferenceResolvesPartialVerses() {
    $this->assertEquals(array(new Range(Verse::parse("39002006b"), Verse::parse("39002009a"))), PHPericope\parse_reference(39, "2:6b-9a"));
    $this->assertEquals(array(new Range(Verse::parse("39002006b"), Verse::parse("39002006b")), new Range(Verse::parse("39002009a"), Verse::parse("39002009a"))), PHPericope\parse_reference(39, "2:6b, 9a"));
  }

  public function testParsingReferenceIgnoresAWhenARangeStartsWithIt() {
    $this->assertEquals(array(new Range(Verse::parse(39002006), Verse::parse(39002009))), PHPericope\parse_reference(39, "2:6a-9"));
  }

  public function testParsingReferenceAllowsRangeToEndInBIfMaxLetterGreaterThanB() {
    Pericope::$max_letter = 'b';
    $this->assertEquals(array(new Range(Verse::parse(39002006), Verse::parse(39002009))), PHPericope\parse_reference(39, "2:6-9b"));
    Pericope::$max_letter = 'd';
    $this->assertEquals(array(new Range(Verse::parse(39002006), Verse::parse("39002009b"))), PHPericope\parse_reference(39, "2:6-9b"));
  }

  public function testParsingReferenceResolvesTwoPartialsInSameVerse() {
    $this->assertEquals(array(new Range(Verse::parse("58009012a"), Verse::parse("58009012a")), new Range(Verse::parse("58009012c"), Verse::parse("58009012c"))), PHPericope\parse_reference(58, "9:12a, c"));
  }

  public function testParsingReferenceWorksCorrectlyOnBooksWithNoChapters() {
    $this->assertEquals(array(new Range(Verse::parse(65001008), Verse::parse(65001010))), PHPericope\parse_reference(65, "8-10"));
  }

  public function testParsingReferenceIgnoresChapterNotationForChapterlessBooks() {
    $this->assertEquals(array(new Range(Verse::parse(65001008), Verse::parse(65001010))), PHPericope\parse_reference(65, "6:8-10"));
  }

  public function testParsingReferenceCoercesVersesToTheRightRange() {
    $this->assertEquals(array(new Range(Verse::parse(41001045), Verse::parse(41001045))), PHPericope\parse_reference(41, "1:452"));
    $this->assertEquals(array(new Range(Verse::parse(41001001), Verse::parse(41001001))), PHPericope\parse_reference(41, "1:0"));
  }

  public function testParsingReferenceCoercesChaptersToTheRightRange() {
    $this->assertEquals(array(new Range(Verse::parse(43021001), Verse::parse(43021001))), PHPericope\parse_reference(43, "28:1"));
    $this->assertEquals(array(new Range(Verse::parse(43001001), Verse::parse(43001001))), PHPericope\parse_reference(43, "0:1"));
  }

  public function testParseOneWorks() {
    $pericope = PHPericope\parse_one("ps 1:1-6");
    $this->assertEquals("Psalm", $pericope->book_name());
    $this->assertEquals(array(new Range(Verse::parse(19001001), Verse::parse(19001006))), $pericope->ranges);
  }

  public function testParseOneWorksWithoutSpaceBetweenBookAndReference() {
    $pericope = PHPericope\parse_one("ps1");
    $this->assertEquals("Psalm", $pericope->book_name());
    $this->assertEquals(array(new Range(Verse::parse(19001001), Verse::parse(19001006))), $pericope->ranges);
  }

  public function testParseOneReturnsNullForInvalidReference() {
    $this->assertNull(PHPericope\parse_one("nope"));
  }

  public function testParseOneIgnoresTextBeforeAndAfterReference() {
    $tests = array(
      'This is some text about 1 Cor 1:1' => '1 Corinthians 1:1',
      '(Jas. 1:13, 20) '                  => 'James 1:13, 20',
      'jn 21:14'                          => 'John 21:14',
      'zech 4:7'                          => 'Zechariah 4:7',
      'mt 12:13. '                        => 'Matthew 12:13',
      'Luke 2---Maris '                   => 'Luke 2',
      'Luke 3"1---Aliquam '               => 'Luke 3:1',
      '(Acts 13:4-20a)'                   => 'Acts 13:4–20a'
    );

    foreach($tests as $input => $expected) {
      $this->assertEquals($expected, PHPericope\parse_one($input)->to_string(), "Expected to find \"$expected\" in \"$input\"");
    }
  }

  public function testToStringStandardizesBookNames() {
    $tests = array(
      'James 4' => array('jas 4'),
      '2 Samuel 7' => array('2 sam 7', 'iisam 7', 'second samuel 7', '2sa 7', '2 sam. 7')
    );

    foreach($tests as $expected => $inputs) {
      foreach($inputs as $input) {
        $pericope = PHPericope\parse_one($input);
        $actual = $pericope->to_string();
        $this->assertEquals($expected, $actual, "Expected Pericope to format $pericope->original_string as $expected; got $actual");
      }
    }
  }

  public function testToStringStandardizesChapterAndVerseNotation() {
    $tests = array(
      'James 4:7'                 => array('jas 4:7', 'james 4:7', 'James 4.7', 'jas 4 :7', 'jas 4: 7'),
      'Mark 1:1b–17; 2:3–5, 17a'  => array('mk 1:1b-17,2:3-5,17a')
    );

    foreach($tests as $expected => $inputs) {
      foreach($inputs as $input) {
        $pericope = PHPericope\parse_one($input);
        $actual = $pericope->to_string();
        $this->assertEquals($expected, $actual, "Expected Pericope to format $pericope->original_string as $expected; got $actual");
      }
    }
  }

  public function testToStringDoesNotRepeatVerseNumberWhenDisplayingPartialsOfSameVerse() {
    $this->assertEquals('John 21:24a, c', PHPericope\parse_one('John 21:24a, 21:24c')->to_string());
  }

  public function testToStringOmitsVersesWhenDescribingEntireChapter() {
    $this->assertEquals('Psalm 1', PHPericope\parse_one('Psalm 1:1-6')->to_string());
  }

  public function testToStringDoesNotConsiderWholeChapterIfRangeExcludesPartOfFirstVerse() {
    $this->assertEquals('Psalm 1:1b–6', PHPericope\parse_one('Psalm 1:1b-6')->to_string());
  }

  public function testToStringdoesNotConsiderWholeChapterIfRangeExcludesPartOfLastVerse() {
    $this->assertEquals('Psalm 1:1–6a', PHPericope\parse_one('Psalm 1:1-6a')->to_string());
  }

  public function testToStringNeverOmitsVersesWhenDescribingAllVersesOfChapterlessBook() {
    $this->assertEquals('Jude 1–25', PHPericope\parse_one('Jude 1-25')->to_string());
  }

  public function testToStringAllowsCustomizingVerseRangeSeparator() {
    $this->assertEquals('John 1:1_7', PHPericope\parse_one('john 1:1-7')->to_string(array('verse_range_separator' => '_')));
  }

  public function testToStringAllowsCustomizingChapterRangeSeparator() {
    $this->assertEquals('John 1_3', PHPericope\parse_one('john 1-3')->to_string(array('chapter_range_separator' => '_')));
  }

  public function testToStringAllowsCustomizingVerseListSeparator() {
    $this->assertEquals('John 1:1_3', PHPericope\parse_one('john 1:1, 3')->to_string(array('verse_list_separator' => '_')));
  }

  public function testToStringAllowsCustomizingChapterListSeparator() {
    $this->assertEquals('John 1:1_3:1', PHPericope\parse_one('john 1:1, 3:1')->to_string(array('chapter_list_separator' => '_')));
  }

  public function testToStringAllowsCustomizingAlwaysPrintVerseRange() {
    $this->assertEquals('John 1', PHPericope\parse_one('john 1')->to_string(array('always_print_verse_range' => false)));
    $this->assertEquals('John 1:1–51', PHPericope\parse_one('john 1')->to_string(array('always_print_verse_range' => true)));
  }

  public function testSplitSplitsTextFromPericopes() {
    $text = "Paul, rom. 12:1-4, Romans 9:7b, 11, Election, Theology of Glory, Theology of the Cross, 1 Cor 15, Resurrection";
    $expected_fragments = array(
      "Paul, ",
      PHPericope\parse_one('rom. 12:1-4'),
      ", ",
      PHPericope\parse_one('Romans 9:7b, 11'),
      ", Election, Theology of Glory, Theology of the Cross, ",
      PHPericope\parse_one('1 Cor 15'),
      ", Resurrection"
    );

    $this->assertEquals($expected_fragments, PHPericope\split($text));
  }

  public function testIntersectsSayWhetherTwoPericopesShareVerses() {
    $tests = array(
      array('exodus 12', 'exodus 12:3-13', 'exodus 12:5'),
      array('3 jn 4-8', '3 jn 7:1-7', '3 jn 5'),
      array('matt 3:5-8', 'matt 3:1-5'),
      array('matt 3:5-8', 'matt 3:8-15')
    );

    foreach($tests as $references) {
      $pericopes = array();
      foreach($references as $reference) $pericopes[] = PHPericope\parse_one($reference);
      $rand_keys = array_rand($pericopes, 2);
      $this->assertTrue($pericopes[$rand_keys[0]]->intersects($pericopes[$rand_keys[1]]));
    }

    $a = PHPericope\parse_one('mark 3-1');
    $b = PHPericope\parse_one('mark 2:1');
    $this->assertFalse($a->intersects($b));
  }

  public function testPericopeAcceptsAnArrayOfVerses() {
    $tests = array(
      'Genesis 1:1'       => array('1001001'),
      'John 20:19–23'     => array('43020019', '43020020', '43020021', '43020022', '43020023'),
      'Psalm 1'           => array('19001001', '19001002', '19001003', '19001004', '19001005', '19001006'),
      'Psalm 122:6—124:2' => array('19122006', '19122007', '19122008', '19122009', '19123001', '19123002', '19123003', '19123004', '19124001', '19124002'),
      'Romans 3:1–4a'     => array('45003001', '45003002', '45003003', '45003004a'),
      'Romans 3:1–4b'     => array('45003001', '45003002', '45003003', '45003004a', '45003004b'),
      'Romans 3:1b–4'     => array('45003001b', '45003001c', '45003001d', '45003002', '45003003', '45003004'),
      'Romans 3:1b, 2–4'  => array('45003001b', '45003002', '45003003', '45003004'),
      'Luke 1:17a, d'     => array('42001017a', '42001017d')
    );

    foreach($tests as $expected_reference => $verses) {
      $this->assertEquals($expected_reference, (new Pericope($verses))->to_string());
    }
  }

  public function testToArrayReturnsAnArrayOfVerses() {
    $tests = array(
      'Genesis 1:1'       => array('1001001'),
      'John 20:19–23'     => array('43020019', '43020020', '43020021', '43020022', '43020023'),
      'Psalm 1'           => array('19001001', '19001002', '19001003', '19001004', '19001005', '19001006'),
      'Psalm 122:6—124:2' => array('19122006', '19122007', '19122008', '19122009', '19123001', '19123002', '19123003', '19123004', '19124001', '19124002'),
      'Romans 3:1–4a'     => array('45003001', '45003002', '45003003', '45003004a'),
      'Romans 3:1–4b'     => array('45003001', '45003002', '45003003', '45003004a', '45003004b'),
      'Romans 3:1b–4'     => array('45003001b', '45003001c', '45003001d', '45003002', '45003003', '45003004'),
      'Romans 3:1b, 2–4'  => array('45003001b', '45003002', '45003003', '45003004'),
      'Luke 1:17a, d'     => array('42001017a', '42001017d')
    );

    foreach($tests as $reference => $expected_verses) {
      $actual_verses = array();
      $pericope = PHPericope\parse_one($reference);
      foreach($pericope->to_array() as $verse) {
        $actual_verses[] = $verse->to_id();
      }
      $this->assertEquals($expected_verses, $actual_verses);
    }
  }

}

?>
