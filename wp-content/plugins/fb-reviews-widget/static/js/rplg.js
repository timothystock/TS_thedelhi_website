function rplg_next_reviews(name, pagin) {
    var parent = this.parentNode,
        selector = '.wp-' + name + '-review.wp-' + name + '-hide';
        reviews = parent.querySelectorAll(selector);
    for (var i = 0; i < pagin && i < reviews.length; i++) {
        reviews[i] && (reviews[i].className = reviews[i].className.replace('wp-' + name + '-hide', ' '));
    }
    reviews = parent.querySelectorAll(selector);
    if (reviews.length < 1) {
        parent.removeChild(this);
    }
    window.rplg_blazy && window.rplg_blazy.revalidate();
    return false;
}

function rplg_leave_review_window() {
    _rplg_popup(this.getAttribute('href'), 620, 500);
    return false;
}

function _rplg_lang() {
    var n = navigator;
    return (n.language || n.systemLanguage || n.userLanguage ||  'en').substr(0, 2).toLowerCase();
}

function _rplg_popup(url, width, height, prms, top, left) {
    top = top || (screen.height/2)-(height/2);
    left = left || (screen.width/2)-(width/2);
    return window.open(url, '', 'location=1,status=1,resizable=yes,width='+width+',height='+height+',top='+top+',left='+left);
}

function _rplg_init_blazy(attempts) {
    if (!window.Blazy) {
        if (attempts > 0) {
            setTimeout(function() { _rplg_init_blazy(attempts - 1); }, 200);
        }
        return;
    }
    window.rplg_blazy = new Blazy({selector: 'img.rplg-blazy'});
}

document.addEventListener('DOMContentLoaded', function() {
    var reviewTimes = document.querySelectorAll('.wpac [data-time]');
    for (var i = 0; i < reviewTimes.length; i++) {
        var clss = reviewTimes[i].className, time;
        if (clss.indexOf('google') > -1) {
            time = parseInt(reviewTimes[i].getAttribute('data-time'));
            time *= 1000;
        } else if (clss.indexOf('facebook') > -1) {
            time = new Date(reviewTimes[i].getAttribute('data-time').replace(/\+\d+$/, '') + '.000Z').getTime();
        } else {
            time = new Date(reviewTimes[i].getAttribute('data-time').replace(/ /, 'T')).getTime();
        }
        reviewTimes[i].innerHTML = WPacTime.getTime(time, _rplg_lang(), 'ago');
    }
    _rplg_init_blazy(10);
});