# eztfl
When's the next London bus? The lightest possible client

Visit this page at http://eztfl.pectw.net/bus/
http://eztfl.pectw.net/bus/53906 for a busy bus stop: Southbound, Park Lane at Marble Arch.

London has been spectacularly lucky with public transport in recent years to the point where Transport For London (TfL) have 
probably THE most effective IT systems I've had the pleasure of working with. 

Much of my work was travelling from site to site on London Buses, and each bus stop has a sign with a bus stop code that TfL
once hoped people would use with SMS to obtain scheduling info. See https://www.btnews.co.uk/article/4078 for an example of
the sign I mean. That code can be used to interact with TfL's website instead now, but poorly: the official landing page is
at https://tfl.gov.uk/travel-information/stations-stops-and-piers/ and takes an astonishing 5.9Mb of bandwidth to do a 
single lookup. Surely this can be improved on?

TfL allow the public to interact with their back-end at https://api.tfl.gov.uk/, so I decided to write my own client, and this
repo is the result. A single lookup here takes 5Kb a saving of 99.9%. 

I also experimented with thinking with RESTful design in mind: one can edit the URL to the bus stop one is stood at, changing
the final few digits saving even the form lookup. 

Finally, I also experimented with server-less storage. The page uses browser facilities to keep track of recent bus stops and
favourited bus stops. 
