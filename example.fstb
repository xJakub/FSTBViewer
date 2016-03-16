model recursion{
  var m, n;
  states a, b, c, d, e, f, stop, stop2;
  
  transition t0 :={
   from    := a ;
   to      := stop;
   guard   := m=0;
   action  := ;
  };

  transition t1 :={
   from    := a ;
   to      := b ;
   guard   := m>0;
   action  := ;
  };

  transition t2 :={
   from    := b ;
   to      := d ;
   guard   := n=0;
   action  := ;
  };

  transition t3 :={
   from    := b ;
   to      := e ;
   guard   := n>0;
   action  := ;
  };

  transition t4 :={
   from    := b ;
   to      := c ;
   guard   := n>0;
   action  := ;
  };

  transition t5 :={
   from    := c ;
   to      := a ;
   guard   := true;
   action  := m' = m,n' = n - 1;
  };

  transition t6 :={
   from    := e ;
   to      := f ;
   guard   := true;
   action  := m' = m - 1,n' = ?;
  };
  
  transition t7 :={
   from    := d ;
   to      := a ;
   guard   := true;
   action  := m' = m - 1,n' = 1;
  };
  
  transition t8 :={
   from    := f ;
   to      := stop2 ;
   guard   := n<0;
   action  := ;
  };
  
  transition t9 :={
   from    := f ;
   to      := a ;
   guard   := n>=0;
   action  := ;
  };
}

strategy xx {
Region init:={ state = a && m>=0 && n>=0};
}
