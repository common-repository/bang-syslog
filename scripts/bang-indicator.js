jQuery(function () {
  jQuery(".bang-indicator").each(function () {
    var brand = jQuery(this).attr('data-brand');
    if (brand == null || brand == "")
      brand = "bang";    
    jQuery(this).parents(".widget").addClass(brand+"-widget");
  });
});
