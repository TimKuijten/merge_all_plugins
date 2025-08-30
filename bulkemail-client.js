jQuery(function($){
  $('#bec-send').on('click', async function(){
    const emails = $('#bec-emails').val().split(/[\n,]+/).map(e=>e.trim()).filter(Boolean);
    const subject = $('#bec-subject').val().trim();
    const body    = $('#bec-body').val().trim();
    if(!emails.length || !subject || !body){
      alert('Please complete emails, subject and body.');
      return;
    }
    const recipients = emails.map(email=>({email}));
    $('#bec-output').text('Sending...');
    try {
      const res = await fetch(KT_BEC.ajax, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action:'kt_abm_send',
          _ajax_nonce:KT_BEC.nonce,
          payload: JSON.stringify({
            recipients,
            subject_template: subject,
            body_template: body,
            from_email:'',
            from_name:''
          })
        })
      });
      const json = await res.json();
      $('#bec-output').text(JSON.stringify(json,null,2));
    } catch(e){
      console.error(e);
      $('#bec-output').text('Error sending request');
    }
  });
});
