<?php

class C {
    use T{
        f1 as private f2;
    }
}
