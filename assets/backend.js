// wait till the DOM is loaded
jQuery(function($) {
  // attach a submit handler to the form
  $('table.calendar tbody a').click(function(event){
    // get the href of the link
    var href = $(this).attr('href');
    // get the parent table cell of the link
    var target = $(event.target);
    // set the new state
    var state = 'all';
    if (event.shiftKey) { state = 'am'; }
    if (event.altKey) { state = 'pm'; }
    // bind an ajax request to the link
    $.ajax({
      type: 'GET',
      url: href + '&call_by=ajax&state='+state,
      dataType: 'text',
      success: function(data){
        if (data == 'deleted') { target.parent('td').removeClass(); }
        else { target.parent('td').removeClass().addClass(data); }
        },
      error: function(xhr,err,e){ alert('Error: ' + err); }
    }); // $.ajax()
    return false;
  }); // .click()
});