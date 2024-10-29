function hrx_regenerate_script_tag(script_id) {
    let script = document.getElementById(script_id);

    let new_script = document.createElement("script");
    new_script.textContent = script.textContent;
    new_script.id = script.id + "-regenerated";
    
    script.parentNode.insertBefore(new_script, script.nextSibling);
    script.parentNode.removeChild(script);
}
