/**
 * @defgroup plugins_generic_lucene_js
 */
/**
 * @file js/LuceneAccordion.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LuceneAccordion
 * @ingroup plugins_generic_lucene_js
 *
 * @brief Javascript for Lucene Facet Accordion
 */


$('.lucenetoggle').click(function(e) {
  e.preventDefault();

  var $this = $(this);

  //if options visible, close options
  if ($this.next().hasClass('show')) {
    $this.next().removeClass('show');
    $this.find('.fa-minus').removeClass('fa-minus').addClass('fa-plus');
  } else { //if options invisible, expand them
    $this.parent().parent().find('ul .inner').removeClass('show');
    $this.parent().parent().find('.fa-plus').removeClass('fa-minus').addClass('fa-plus');
    $this.next().toggleClass('show');
    $this.find('.fa-plus').removeClass('fa-plus').addClass('fa-minus');
  }
});



