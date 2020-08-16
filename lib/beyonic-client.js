/*
 * Copyright (c) 2020. one-tech junction. written by akankwatsa Johnson(jakankwasa.tech@yahoo.com)
 */

const TIMEOUT = 5000; // timeout interval to check api for status
let clientInterval = null;
jQuery(document).ready(function ($) {

    const fn = () => makeAjaxRequest($);
    clientInterval = setInterval(fn, TIMEOUT); //check status of request at TIMEOUT intervals
});

function makeAjaxRequest($) {
    const retryForm = $('#retryForm'); // request failed
    const depositForm = $('#depositForm'); // no request yet
    const depositErrorForm = $('#depositErrorForm'); // no request yet
    if (retryForm.length || depositForm.length || depositErrorForm.length) {
        clearInterval(clientInterval);
        return;
    }
    let data = {'action': beyonicObj.action, 'nonce': beyonicObj.nonce};
    if (beyonicObj.depositPhoneNo) {
        data = {
            'action': beyonicObj.action,
            'nonce': beyonicObj.nonce,
            'depositAmount': beyonicObj.depositAmount,
            'depositPhoneNo': beyonicObj.depositPhoneNo,
            'uid': beyonicObj.uid,
        }
    }

    // make ajax request
    $.ajax({
        url: beyonicObj.ajaxUrl, // url passed to beyonicObj created when enqueueing script
        data,
        success: function (s) {
            s = parseInt(s);
            if ((s !== 0) && !(beyonicObj.depositAmount)) { // if request doesn't exist or finished
                clearInterval(clientInterval);
            }
            const completeFormBtn = $('#completeFormBtn'); // request finished submit complete form
            if (completeFormBtn.length && s > 0) {
                completeFormBtn.click();
            }

        },
        error: function (e) {
            // console.log(e);
            clearInterval(clientInterval);
        }
    });
}
