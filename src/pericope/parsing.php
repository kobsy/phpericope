<?php
namespace PHPericope;

class ReferenceFragment {
  public $chapter;
  public $verse;
  public $letter;

  public function __construct($chapter=null, $verse=null, $letter=null) {
    $this->chapter = $chapter;
    $this->verse = $verse;
    $this->letter = $letter;
  }

  public function needs_verse() {
    return is_null($this->verse);
  }

  public function to_verse($book) {
    return new Verse($book, $this->chapter, $this->verse, $this->letter);
  }
}

function parse_one($text) {
  $pericopes = parse($text);
  return (count($pericopes) > 0) ? $pericopes[0] : null;
}

function parse($text) {
  $pericopes = array();
  foreach(match_all($text) as $matched) {
    $pericopes[] = new Pericope($matched);
  }
  return $pericopes;
}

function split($text) {
  $segments = array();
  $start = 0;

  foreach(match_all($text) as $matched) {
    $pretext = substr($text, $start, $matched['offset'] - $start);
    if(sizeof($pretext) > 0) $segments[] = $pretext;

    $pericope = new Pericope($matched);
    $segments[] = $pericope;

    $start = $matched['offset'] + sizeof($matched['original_string']);
  }

  $posttext = substr($text, $start, sizeof($text) - $start);
  if(sizeof($posttext) > 0) $segments[] = $pretext;

  return $segments;
}


function match_one($text) {
  $matched = match_all($text);
  return count($matched) == 0 ? null : $matched[0];
}

function match_all($text) {
  $matched = array();
  preg_match_all(Pericope::regexp(), $text, $matches, PREG_OFFSET_CAPTURE);
  foreach($matches as $match) {
    $full_match = array_shift($match);
    $book_index = 0;
    if(sizeof($match[0][0]) == 0) {
      while(sizeof(next($match)[0]) == 0) ;
      $book_index = key($match);
    }
    $book = BOOK_IDS[$book_index];

    $ranges = parse_reference($book, $match[67][0]);

    if(count($ranges) > 0) {
      $matched[] = array('original_string' => $full_match[0], 'book' => $book, 'ranges' => $ranges, 'offset' => $full_match[1]);
    }
  }

  return $matched;
}

function parse_reference($book, $reference) {
  return parse_ranges($book, preg_split(normalize_reference($reference), '/[,;]'));
}

function normalize_reference($reference) {
  foreach(Pericope::normalizations() as $normalization) {
    $reference = preg_replace($normalization['pattern'], $normalization['replacement'], $reference);
  }
  return $reference;
}

function parse_ranges($book, $ranges) {
  $default_chapter = null;
  if(Pericope::has_chapters($book)) $default_chapter = 1;
  $default_verse = null;

  $parsed_ranges = array();
  foreach($ranges as $range) {
    $range_ends = explode('-', $range, 2);
    $range_begin_string = $range_ends[0];
    $range_end_string = $range_begin_string;
    if(sizeof($range_ends) > 1) $range_end_string = $range_ends[1];

    $range_begin = parse_reference_fragment($range_begin_string, $default_chapter, $default_verse);

    // no verse specified; this is a range of chapters, so start with verse 1
    $chapter_range = false;
    if($range_begin->needs_verse()) {
      $range_begin->verse = 1;
      $chapter_range = true;
    }

    $range_begin->chapter = to_valid_chapter($book, $range_begin->chapter);
    $range_begin->verse = to_valid_verse($book, $range_begin->chapter, $range_begin->verse);

    $range_end = null;
    if($range_begin_string == $range_end_string && !$chapter_range) {
      $range_end = clone $range_begin;
    } else {
      $range_end = parse_reference_fragment($range_end_string, ($chapter_range ? $range_begin->chapter : null));
      $range_end->chapter = to_valid_chapter($book, $range_end->chapter);

      // treat Mark 3-1 as Mark 3-3 and, eventually, Mark 3:1-35
      if($range_end->chapter < $range_begin->chapter) $range_end->chapter = $range_begin->chapter;

      // if this is a range of chapters, end with the last verse
      if($range_end->needs_verse()) {
        $range_end->verse = Pericope::get_max_verse($book, $range_end->chapter);
      } else {
        $range_end->verse = to_valid_verse($book, $range_end->chapter, $range_end->verse);
      }
    }

    // e.g., parsing 11 in 12:1-8, 11 => remember that 12 is the chapter
    $default_chapter = $range_end->chapter;

    // e.g., parsing c in 9:12a, c => remember that 12 is the verse
    $default_verse = $range_end->verse;

    $range = new Range($range_begin->to_verse($book), $range_end->to_verse($book));

    // an 'a' at the beginning of a range is redundant
    if($range->begin->letter == 'a' && $range->end->number() > $range->begin->number()) $range->begin->letter = null;

    // a 'd' at the end of a range is redundant
    if($range->end->letter == Pericope::$max_letter && $range->end->number() > $range->begin->number()) $range->end->letter = null;

    $parsed_ranges[] = $range;
  }

  return $parsed_ranges;
}

function parse_reference_fragment($input, $default_chapter=null, $default_verse=null) {
  preg_match(Pericope::fragment_regexp(), $input, $matches, PREG_UNMATCHED_AS_NULL);
  $chapter = $matches['chapter'];
  $verse = $matches['verse'];
  $letter = $matches['letter'];
  if(is_null($chapter)) $chapter = $default_chapter;
  if(is_null($chapter)) {
    $chapter = $verse;
    $verse = null;
  }
  if(is_null($verse)) $verse = $default_verse;
  if(is_null($verse)) $letter = null;

  return new ReferenceFragment(intval($chapter), is_null($verse) ? null : intval(verse), $letter);
}

function to_valid_chapter($book, $chapter) {
  return coerce_to_range($chapter, 1, Pericope::get_max_chapter($book));
}

function to_valid_verse($book, $chapter, $verse) {
  return coerce_to_range($verse, 1, Pericope::get_max_verse($book, $chapter));
}

function coerce_to_range($number, $begin, $end) {
  if($number < $begin) return $begin;
  if($number > $end) return $end;
  return $number;
}

?>
