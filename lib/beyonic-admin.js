/*
 * Copyright (c) 2020. one-tech junction. written by akankwatsa Johnson(jakankwasa.tech@yahoo.com)
 */
const TIMEOUT = 5000; // timeout interval to check api for status
const COLORS = {0: '#ffffb8', 1: '#cff7cf', 2: '#f9dbdf'}; // background colors for table rows
const STATUSTEXT = 'beyonicStatus'; // attribute used to track status
let adminInterval = null;
jQuery(document).ready(function ($) {
    const links = $('a.awesome'); // select all links with class awesome, change this when theme gets updated
    const ids = [];
    // Extract row Ids from the withdraws table
    if (links.length > 0) {
        for (let i = 0; i < links.length; i++) {
            // extract id from query string
            const params = new URLSearchParams(links[i].href);
            const id = params.get('tid');
            if (id) {
                ids.push(parseInt(id)); // convert to numbers and add to ids array
            }
        }
    }
    const fn = () => makeAjaxRequest(ids, links);
    adminInterval = setInterval(fn, TIMEOUT); // check for status changes every timeOut milliseconds
});

function makeAjaxRequest(ids, links) {
    if (ids.length === 0) {
        clearInterval(adminInterval); // table empty stop messing around
        return;
    }
    // filter ids and remove completed and failed ones, to avoid making endless requests to the server
    for (let i = 0; i < ids.length; i++) {
        let st = getRow(i, links).getAttribute(STATUSTEXT);
        if ((st !== null) || (st !== undefined)) {
            st = parseInt(st);
            if ((st === 1 || st === 2)) {
                ids.splice(i, 1);
            }
        }
    }
    if (ids.length === 0) {
        return;
    }
    // make ajax request
    $.ajax({
        url: beyonicObj.ajaxUrl, // url passed to beyonicObj created when enqueueing script
        data: {
            'action': beyonicObj.action, // name of php function that will handle requests
            'ids': ids,
            'nonce': beyonicObj.nonce
        },
        success: function (d) {
            if (d) {
                if (d.length === 0) { // if results empty, clear ids since non has been submitted yet
                    ids.splice(0, ids.length);
                }
                // update table according to result
                for (let j = 0; j < ids.length; j++) {
                    changeBgColor(d[ids[j]], j, links); // change bg color of row according to status
                }
            }
        },
        error: function (e) {
            // console.log(e);
            clearInterval(adminInterval);
        }
    });
}

function changeBgColor(status, index, links) {
    if (status === null || status === undefined) {
        return;
    }
    const row = getRow(index, links);
    row.style.backgroundColor = COLORS[status];
    // set status attribute so that this row is omitted on the next cycle
    row.setAttribute(STATUSTEXT, status);
}

function getRow(index, links) {
    return links[index].parentElement.parentElement;
}
